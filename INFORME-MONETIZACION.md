# INFORME — Monetización dropshipping, sourcing de productos y favoritos

Fecha: 2026-11-21 · Laravel 13.43 · PHP 8.4

Esta iteración responde a tres peticiones: **(1)** una lista de favoritos
(wishlist) para guardar productos, **(2)** conseguir productos para la tienda
sin scraping ilegal, y **(3)** aplicar el método real con el que ganan dinero
las tiendas dropshipping. El método de monetización (margen + comisión de
afiliado + libro de liquidaciones) ya estaba modelado; aquí se completa con
el **importador de feed con markup** y se documenta de extremo a extremo con
cifras reales de la industria.

---

## 1. Cómo gana dinero una tienda dropshipping (el método aplicado)

Las tiendas dropshipping reales monetizan combinando dos flujos:

1. **Margen sobre coste (markup)**. El producto se lista a un precio de venta
   `price` mayor que el `cost_price` que el promotor paga al proveedor. La
   diferencia `price − cost_price` es el **margen bruto** por unidad. Es el
   modelo clásico: el cliente te paga a ti, tú compras al proveedor más barato
   y te quedas la diferencia.
2. **Comisión de afiliación**. Si el producto se canaliza a una tienda con
   programa de afiliados (AliExpress, Amazon, Shein…), la tienda paga además
   una comisión sobre el precio de venta. No requiere inventario ni
   fulfillment: rediriges al cliente y cobras el %.

MODA implementa **ambos**:

- `Product::unit_profit = current_price − cost_price` (margen unitario).
- `Product::unit_commission = current_price × commission_rate` (comisión %,
  o fija si `commission_type=fixed`).
- En el checkout, `CheckoutController` suma por item:
  `Order::margin_total = Σ unit_profit × qty` y
  `Order::commission_total = Σ unit_commission × qty` para los productos
  dropshipping.
- `SettlementService::createForOrder()` genera tres asientos contables por
  pedido (dentro de la transacción del checkout, atómico):

  | Asiento (`settlements.type`) | Significado | Estado inicial |
  | --- | --- | --- |
  | `receivable_from_customer` | lo que el cliente nos debe (propios + envío) | `settled` si pagó con billetera; si no, `pending` |
  | `commission_owed_by_store` | comisión que la tienda externa nos debe | `pending` (hasta postback de la tienda) |
  | `payable_to_supplier` | lo que debemos al proveedor (productos propios con `cost_price`) | `pending` |

  El admin liquida desde `/admin/settlements` y el dashboard muestra los
  agregados: por cobrar / comisión por cobrar / por pagar / margen realizado
  / comisión realizada.

- `AffiliateRedirectController` es el **punto único de salida** a la tienda
  externa: valida el dominio contra una *whitelist* (anti open-redirect),
  inyecta el `affiliate_tag` en la URL (`?tag=`, `?aff_fcid=`, `?url_from=`
  según la tienda) y registra un `AffiliateClick` para auditoría de
  comisiones con `estimated_commission = unit_commission + unit_profit`.

**Cifras reales de la industria** (para calibrar el markup por defecto):

- Margen neto realista en dropshipping: **10–30 %** tras costes, envío y
  fees; los principiantes suelen estar por debajo del 15 % (Printful,
  *How profitable is dropshipping*).
- Los dropshippers con más éxito hacen **20–30 % de margen por transacción**
  (reporte BlueCard citado en *Dropshipping Statistics 2026*); en low-ticket
  se ven márgenes del orden del 50 %.
- Comisiones de afiliación: **AliExpress Portals hasta 9 %** básica (90 % en
  *Hot Products*); **Amazon Associates hasta 10 %** (la mayoría de
  categorías 1–4 %).

El factor de markup por defecto es `DROPSHIP_MARKUP_FACTOR=1.40` (+40 % sobre
coste ≈ 29 % de margen bruto), dentro del rango sano y configurable por nicho
(low-ticket puede subir a 1.6–2.0). Las comisiones se fijan por producto en
el feed.

---

## 2. Sourcing de productos: feed importer (no scraping de tiendas)

**Por qué NO se scrapean las tiendas directamente.** Scrapear las páginas de
producto de Shein, Zara, AliExpress o Amazon viola sus términos de servicio,
reutiliza imágenes con copyright y expone a la tienda a demandas/tiendas
bloqueadas. No es la vía que usan las tiendas dropshipping reales.

Lo que sí usan son los **feeds de catálogo** que los programas de afiliados y
los proveedores publican **para que sus socios los importen**:

| Fuente | Formato | Comisión |
| --- | --- | --- |
| AliExpress Portals Affiliate | JSON / API | hasta 9 % (90 % Hot Products) |
| Amazon Product Advertising API | JSON / API | hasta 10 % |
| Awin / CJ Affiliate / Rakuten | CSV / XML | variable por anunciante |
| Spocket / Modalyst / DSers | JSON | por proveedor |

El comando `products:import` lee uno de esos feeds (JSON o CSV, local o
remoto) y, por cada producto:

- lo resuelve por `(external_store, external_product_id)` → crea o actualiza,
- calcula el precio de venta con markup (`cost_price × factor`, redondeo
  psicológico a 5 céntimos, mínimo 1 €) si el feed trae `cost_price`; si sólo
  trae `price` (modo afiliado puro), lo usa tal cual,
- rellena `affiliate_tag`, `commission_rate`/`commission_type`, `external_url`,
  imagen y categoría (find-or-create por slug),
- marca `is_dropshipping=true` → el checkout calcula `margin_total` y
  `commission_total`,
- invalida la caché de catálogo y home (`bumpVersions`) tras importar.

Acepta los sinónimos de campo habituales de los feeds reales (`title`/`name`,
`cost`/`cost_price`, `image`/`image_url`, `link`/`url`…).

```bash
# Importar el feed de ejemplo (datos sintéticos, para probar):
php artisan products:import --path=database/feeds/sample_products.json

# Importar un feed real remoto:
php artisan products:import --url=https://feed.proveedor.com/catalog.json

# Simular sin escribir + sobreescribir el tag de afiliado:
php artisan products:import --path=feed.csv --format=csv --dry --tag=misaffid
```

Configuración (`config/dropship.php` → `pricing` e `import`):

```
DROPSHIP_MARKUP_FACTOR=1.40      # +40 % sobre coste
DROPSHIP_MIN_PRICE=1.00          # no bajar de 1 €
DROPSHIP_ROUND_TO=0.05           # redondeo a 5 céntimos
DROPSHIP_AFFILIATE_TAG=moda-aff  # tag por defecto si el feed no trae
DROPSHIP_DEFAULT_COMMISSION=6.00 # % por defecto
DROPSHIP_IMPORT_TIMEOUT=30       # seg al descargar feed remoto
```

El feed de ejemplo `database/feeds/sample_products.json` incluye seis
productos en modo markup (con `cost_price`) y uno en modo afiliado (sólo
`price`), repartidos entre aliexpress, amazon, shein y asos.

> Nota: el snapshot vivo de cada producto (precio/stock/disponibilidad en la
> tienda externa) sigue gestionándolo `cache:external-products`
> (`external_product_cache`), que sobrevive a reinicios/caídas. El importer
> puebla el catálogo; el cache externo lo refresca.

---

## 3. Favoritos / wishlist (lista de productos favoritos)

Lista de favoritos **persistente en BD** (sobrevive a reinicios y caídas,
igual que el carrito DB):

- Tabla `wishlists` con par único `(user_id, product_id)` → no duplica.
- Aislamiento: el `WishlistController` filtra siempre por `auth()->id()`; las
  rutas operan por `product_id` (no por id de fila ajena) → sin IDOR. El
  borrado de cuenta elimina los favoritos (RGPD, `AccountDeletionService`).
- Rutas (auth): `GET /favoritos` (página), `GET /favoritos/ids` (JSON),
  `POST /favoritos/{product}` (toggle), `DELETE /favoritos/{product}`.

**Por qué el corazón es JS-driven.** Las tarjetas de producto y el detalle
se cachean como HTML (`CacheService`). El estado "¿es favorito de ESTE
usuario?" depende del usuario y **no se puede hornear** en el HTML cacheado
(cada usuario tendría el suyo), ni el token CSRF del formulario puede
congelarse (causaría 419). Por eso:

- el botón corazón es **neutro** (`data-wishlist` + `data-wishlist-id` +
  `data-wishlist-url`, URL determinista por producto → sí cacheable),
- el layout inyecta `window.wishlistIds` (un `pluck` por request para auth) y
  `window.isUserAuth`/`window.loginUrl`,
- `resources/js/wishlist.js` rellena los corazones (`.is-active`), alterna
  por `fetch` POST con el token CSRF del `<meta>` y actualiza el badge del
  header (`[data-wishlist-count]`). Invitados → redirige a login.

UI: corazón en cada tarjeta y en el detalle, badge de conteo en el header
(junto al carrito) y página `/favoritos` con grid + paginación + estado vacío.

---

## 4. Despliegue

```bash
# 1. Copiar el ZIP sobre el proyecto.
# 2. Migración nueva (wishlists) + reconstruir assets:
php artisan migrate
npm run build        # compila resources/js/wishlist.js + CSS

# 3. Limpiar caches (esquema de caché + vistas compiladas):
php artisan optimize:clear

# 4. Importar productos (feed de ejemplo) + refrescar caché externo:
php artisan products:import --path=database/feeds/sample_products.json
php artisan cache:external-products
php artisan cache:warm
```

---

## 5. Resumen de ficheros de esta iteración

- **Migración**: `create_wishlists_table`.
- **Modelo**: `Wishlist`. **Editado**: `User` (relaciones `wishlist`,
  `wishlistProducts`, `wishlistProductIds`).
- **Controlador**: `WishlistController`. **Editado**: `AccountDeletionService`
  (borra favoritos + auditoría).
- **Comando**: `ImportProducts` (`products:import`). **Config**: `dropship.php`
  (secciones `pricing` e `import`). **Feed**: `database/feeds/sample_products.json`.
- **Vistas**: `wishlist/index`, `layouts/app` (inyección `window.wishlistIds` +
  badge), `partials/product-card` y `partials/products/show-content` (corazón
  `data-wishlist`).
- **JS/CSS**: `resources/js/wishlist.js` (registrado en `app.js`),
  `resources/css/app.css` (`.wishlist-btn.is-active`).
- **Rutas**: bloque `wishlist.*` (auth) en `routes/web.php`.
