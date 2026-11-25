# INFORME — Iteración 2026-11-25: Ads, pago con tarjeta, afiliación y clicks

Fecha: 2026-11-25 · Laravel 13.x · PHP 8.3+

Esta iteración completa las cuatro piezas semiterminadas de la web:

1. **Sistema de anuncios (ads)**: existía el componente de vista `ad-slot`
   pero era un stub (un comentario con "pega aquí el script") sin modelo,
   sin tabla, sin panel de gestión y sin integración real en el frontend.
2. **Pago real**: sólo funcionaba la billetera. El checkout ofrecía un
   valor "Pago externo (demo)" que ni siquiera estaba en la lista de
   validación del servidor, y las tarjetas guardadas se consultaban pero
   nunca se renderizaban ni se cobraban.
3. **Afiliación**: las redes de `app/Services/Affiliate/Networks` estaban
   mal conformadas (comparación case-sensitive contra valores en minúsculas,
   un `return true;` hardcodeado en la verificación de firma de AliExpress)
   y el procesamiento de los datos de las empresas afiliadas daba errores
   en conciliación, top tiendas y URLs de afiliado.
4. **Clicks de afiliación**: no se registraban.

---

## 1. Fix del registro de clicks (causa raíz)

La migración original `create_affiliate_clicks_table` definió
`redirect_url TEXT NOT NULL` sin default, pero
`AffiliateRedirectController` nunca rellenaba esa columna (ni
`external_store`, `sale_price` o `estimated_commission`, que además
faltaban en el `$fillable` del modelo). Resultado: cada clic ejecutaba un
INSERT fallido con *"NOT NULL constraint failed: affiliate_clicks.redirect_url"*,
la excepción se tragaba con `report()` y la redirección seguía su curso —
el usuario llegaba a la tienda externa pero **el clic nunca quedaba en la
BD** (y por tanto tampoco las comisiones, ni el sub_id al que vincular el
postback de conversión).

**Correcciones:**

- `AffiliateRedirectController` rellena ahora todas las columnas de
  auditoría: `redirect_url` (URL final con tag+sub_id), `external_store`
  (slug normalizado), `sale_price` (`current_price`),
  `estimated_commission` (`unit_commission + unit_profit`, como documenta
  el informe de monetización) y `commission_amount`.
- `AffiliateClick::$fillable` y `$casts` ampliados con esas columnas.
- Migración `2026_11_25_000001_fix_affiliate_clicks_columns`:
  `redirect_url` → nullable (defensivo) + índice `(network, created_at)`
  para la conciliación por red.

**Verificación en local tras migrar:**

```
GET /ir/{slug-de-producto-dropshipping}
→ affiliate_clicks tiene una fila nueva con sub_id, network, redirect_url…
```

---

## 2. Afiliación: redes bien conformadas + procesamiento de datos

**Problema de fondo:** el seeder y el importador de feeds guardan
`products.external_store` en **minúsculas** (`'shein'`, `'aliexpress'`,
`'amazon'`, `'zara'`, `'asos'`), pero el código comparaba contra
`'Amazon' / 'AliExpress' / 'Shein'` capitalizados. Cada pieza usaba su
propia lista y su propio formato.

**Solución: una única fuente de verdad.**

- Nuevo `App\Services\Affiliate\AffiliateNetworkManager`:
  - `normalize()` — cualquier entrada ('Amazon', 'AMAZON', ' aliexpress ')
    → slug en minúsculas.
  - `resolve($store)` — red dedicada si existe; `GenericNetwork`
    (nueva, params `affid`+`sub_id`) para cualquier otra tienda con
    programa de afiliados (zara, asos…) de modo que el click SIEMPRE
    quede rastreable.
  - `options()` — lista slug→nombre para filtros de la UI.
- `Product::affiliate_url` reescrito: delega en la red correspondiente
  (case-insensitive) y hace **merge correcto de query params** (nunca
  duplica `?` ni machaca parámetros del proveedor; antes
  `...?id=5?affid=null&sub_id=` rompía los enlaces).
- Redes: contrato `AffiliateNetwork` extendido con `slug()` y
  `buildAffiliateUrl()`; base común `AbstractAffiliateNetwork` con el
  merge de query params y comparación de firma en tiempo constante.
  - Amazon: `tag` + `ascsubtag` (sub-tracking).
  - AliExpress: `aff_fcid` + `aff_platform` + `sub_id`.
  - Shein: `url_from` + `sub_id`.
- **FIX de seguridad en AliExpressNetwork::verify()**: tenía un
  `return true;` hardcodeado (el chequeo MD5 real estaba comentado) —
  aceptaba webhooks sin firmar. Ahora verifica el parámetro `sign` y sin
  secreto configurado rechaza (fail-closed), igual que Amazon y Shein.
- Webhook de afiliación: usa el manager, normaliza la red del click
  ('Amazon' → 'amazon'), ignora clics rechazados en conciliación y
  respeta la comisión real del postback (sólo si es > 0).
- Conciliación admin: el filtro "Red" enviaba `'Amazon'` capitalizado y
  comparaba contra `network` en minúsculas → **0 resultados siempre**.
  Ahora filtra por slug normalizado (`scopeForNetwork`) y las opciones
  salen del manager.
- Admin afiliación: miniaturas de "Top productos" usaban
  `$product->image` (ruta cruda, imagen rota) → `image_url`. "Top
  tiendas" usa `COALESCE(network, products.external_store)`.
- `AffiliateRedirectController`: whitelist de hosts ahora sale de
  `config('dropship.allowed_hosts')` (única fuente de verdad, ya incluía
  zara/asos) + variantes regionales es.*.
- **CTA "Comprar en {Tienda}" añadido al detalle de producto**: antes el
  click de afiliado sólo se capturaba desde la home y desde el
  post-checkout; la ficha del producto (la página con más intención de
  compra) no tenía salida de afiliado.

---

## 3. Pago con tarjeta real (Stripe REST, sin SDK)

**Problema:** la vista de checkout ofrecía un radio `value="external"`
"Pago externo (demo)" que no existe en la validación
`in:card,paypal,wallet,cod` → **el submit fallaba para todo método que
no fuera billetera**. Además `savedMethods` se pasaba a la vista pero no
se renderizaba, y no había ningún gateway.

**Arquitectura nueva** (`app/Services/Payment/`):

| Clase | Rol |
| --- | --- |
| `Contracts/PaymentGateway` | Interfaz: `charge`, `refund`, `verifyWebhook`. |
| `StripeGateway` | **Pasarela real**: API REST de Stripe vía Laravel HTTP client (sin SDK). Tokeniza la tarjeta (`POST /v1/tokens`), crea y confirma el `PaymentIntent` (`POST /v1/payment_intents`) con metadata del pedido, mapea los errores de tarjeta a mensajes aptos para el usuario. |
| `SandboxGateway` | Se activa automáticamente si no hay `STRIPE_SECRET`: determinista (Luhn + caducidad + CVC), mismas tarjetas de test de Stripe (4242… OK, 4000…0002 rechazada, 4000…9995 sin fondos). Sin cargo real. |
| `PaymentService` | Orquestador: elige gateway, cobra el importe `chargeable`, guarda tarjetas, verifica webhooks. |
| `PaymentResult` | DTO inmutable (éxito, transacción, error, código). |

**Flujo de checkout (order-first, patrón estándar):**

1. Se crea el pedido en una transacción (stock + débito de billetera +
   asientos contables), con `payment_status=pending` si paga con tarjeta.
2. **Después del commit** se cobra la tarjeta por el importe `chargeable`
   (propios + envío; el dropshipping se paga en la tienda externa).
3. Éxito → `payment_status=paid`, `external_refs` con la transacción, y
   el asiento "por cobrar del cliente" se liquida
   (`settleReceivable`, nuevo en `SettlementService`).
4. Rechazo → el pedido se cancela (`failed`), **se repone el stock** y
   el usuario vuelve al formulario con el mensaje del banco.
5. Si el usuario marca "guardar tarjeta", se crea el `PaymentMethod`
   (token opaco + last4, nunca PAN/CVC) reutilizable en futuros checkouts.

**Otros fixes de pago:**

- La vista compara el saldo de la billetera contra `$chargeable` (antes
  contra `$total`, que incluye el dropshipping que no pagamos nosotros —
  bloqueaba la billetera en carritos mixtos).
- Vista de checkout reescrita: radios Tarjeta / Billetera / Contra
  reembolso, formulario de tarjeta con validación, selector de tarjetas
  guardadas (toggle Alpine "usar otra tarjeta"), opción "guardar
  tarjeta", aviso de importe cobrado y de dropshipping. En sandbox se
  muestran las tarjetas de prueba.
- **Gestión de métodos de pago en el perfil** (nueva sección en
  `/cuenta/perfil`): las rutas `payment-methods.*` existían pero no
  había UI para crear/predeterminar/eliminar.
- **Webhook de Stripe** `POST /webhooks/stripe` (CSRF excluido, firma
  `Stripe-Signature` HMAC-SHA256 con tolerancia anti-replay de 5 min):
  marca pedidos como pagados (pagos asíncronos / 3D Secure) y liquida
  sus asientos. Idempotente.
- Config: `services.stripe` (key/secret/webhook_secret/currency) +
  `.env`/`.env.example` documentados.

**PayPal** queda como hoy: aceptado en validación pero sin cobro (el
pedido nace pendiente); no se ofrece en la vista para no prometer una
vía inexistente.

---

## 4. Sistema de anuncios (ads) completo

| Pieza | Contenido |
| --- | --- |
| Migración `create_ads_table` | name, provider, **position**, type (`image`\|`html`), html/image_url/target_url, is_active, ventana starts_at/ends_at, **impressions**, **clicks**, sort_order. |
| Modelo `Ad` | scopes `serving()` (activo + ventana de vigencia + orden), constantes de posiciones/tipos, CTR calculado. |
| `AdsService` | Resuelve el anuncio por posición con caché versionada (namespace `ads`, TTL 5 min, cache negativa con centinela). `bumpVersion('ads')` al mutar. Respeta el master switch `ADS_ENABLED`. |
| Rutas de tracking | `GET /ads/click/{ad}` (clicks++ + redirect validado: interno o http(s), nunca `javascript:`) y `GET /ads/impression/{ad}` (píxel GIF 1x1 + impressions++). Throttleadas. |
| Componente `<x-ad-slot>` | Reescrito: renderiza el anuncio de la posición (imagen+enlace con tracking, o snippet HTML de proveedor), o el script externo de respaldo (`ADS_SCRIPT`) si no hay anuncio en BD. Las **impresiones se miden con píxel** porque el HTML de home/catálogo se cachea: la URL es determinística por anuncio y funciona igual desde la caché. |
| Posiciones integradas | `home-mid` y `home-flash` (home), `catalog-top` (listado), `product-mid` (ficha), `cart-top` (carrito), `footer-bar` (layout, ya existía). |
| Admin `/admin/anuncios` | CRUD completo (crear/editar/activar/eliminar) + listado con métricas por posición (impresiones, clicks, CTR) y resumen por posición. Enlace "Anuncios" en el sidebar del panel. |
| Seeder `AdSeeder` | 4 banners de ejemplo con SVGs locales nuevos (`public/img/ads/banner-1/2/3.svg`, sin dependencias externas). |
| Assets | Banners SVG locales + `[x-cloak]` en app.css (anti-flash del formulario Alpine). `npm run build` regenerado. |

**Modo proveedor externo** (Adsterra/Monetag): además de snippets `html`
gestionados en el panel, se puede definir `ADS_PROVIDER`/`ADS_SCRIPT`
como respaldo para posiciones sin anuncio propio.

---

## 5. Cómo probarlo

```bash
php artisan migrate            # 2 migraciones nuevas
php artisan db:seed --class=AdSeeder
php artisan optimize:clear

# Clicks de afiliación: abrir /ir/{slug} de un producto dropshipping y
# comprobar la fila en affiliate_clicks (sub_id, network, redirect_url…).

# Conciliación: /admin/afiliacion/conciliacion → filtrar por red
# (amazon/aliexpress/shein/generic) ya devuelve resultados.

# Webhook de afiliación (con secreto configurado en .env):
curl -X POST http://localhost:8000/webhooks/affiliate/aliexpress \
  -H 'Content-Type: application/json' \
  -d '{"sub_id":"<sub_id de un click>","order_number":"EXT-1","commission":1.23,"sign":"<md5>"}'

# Pago con tarjeta (sin STRIPE_SECRET → sandbox):
#   4242 4242 4242 4242 → éxito (pedido paid + settlement liquidado)
#   4000 0000 0000 0002 → rechazada (pedido cancelado + stock repuesto)
#   4000 0000 0000 9995 → saldo insuficiente

# Ads: la home/catálogo/ficha/carrito muestran los banners del seeder;
# /admin/anuncios gestiona posiciones y muestra impresiones/clicks/CTR.
```

---

## 6. Resumen de ficheros

**Nuevos**: `app/Services/Affiliate/AffiliateNetworkManager`,
`app/Services/Affiliate/Networks/{AbstractAffiliateNetwork,GenericNetwork}`,
`app/Services/Payment/{PaymentResult,PaymentService,SandboxGateway,StripeGateway,Contracts/PaymentGateway}`,
`app/Services/AdsService`, `app/Models/Ad`,
`app/Http/Controllers/{AdTrackingController,StripeWebhookController}`,
`app/Http/Controllers/Admin/AdController`,
`resources/views/admin/ads/{index,form}`,
migraciones `fix_affiliate_clicks_columns` + `create_ads_table`,
`database/seeders/AdSeeder`, `public/img/ads/banner-{1,2,3}.svg`.

**Editados**: `Product` (affiliate_url), `AffiliateClick` (fillable+scope),
`AffiliateRedirectController`, `AffiliateWebhookController`,
`Admin/AffiliateController`, `CheckoutController` (flujo tarjeta),
`SettlementService` (paid_upfront + settleReceivable), `CacheService`
(namespace ads), `config/{services,dropship}`, `bootstrap/app.php`,
`routes/web.php`, vistas: `checkout/index`, `account/profile`,
`components/ad-slot`, `partials/home/content`,
`partials/products/{index,show}-content`, `cart/index`,
`admin/affiliate/{index,reconciliation}`, `admin/layouts/app`,
`resources/css/app.css`, `.env`, `.env.example`, `DEPLOY.md`.

**Nota de entorno**: el sandbox de esta iteración no tiene PHP, por lo
que no se pudo ejecutar `php artisan migrate` ni la suite de tests aquí;
la verificación se hizo con chequeos estáticos (balance de sintaxis,
consistencia de rutas/vistas/variables) y `npm run build` (OK). Ejecutar
los pasos de la sección 5 en local/producción.
