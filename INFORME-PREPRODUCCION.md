# INFORME DE PRE-PRODUCCIÓN — MODA

Fecha: 2026-11-21 · Laravel 13.43 · PHP 8.4

Este documento describe las funcionalidades añadidas en esta iteración, el
modelo de negocio implementado y las medidas de seguridad/privacidad. Está
pensado para revisión interna antes del paso a producción.

---

## 1. Modelo de negocio: dropshipping + afiliación

La estrategia que solicitaste se conoce como **dropshipping con afiliación**:
tú muestras productos de tiendas reales (Shein, AliExpress, Amazon, Zara) con
un precio marcado; al comprar, el usuario es redirigido a la tienda original
a través de un enlace de afiliado. La tienda gestiona el pago y el envío;
MODA gana **el margen** (precio de venta − coste) y, si la tienda tiene
programa de afiliados, **una comisión** sobre la venta.

### Esquema de datos (products)

| Campo | Tipo | Descripción |
| --- | --- | --- |
| `is_dropshipping` | bool | Marca el producto como canalizado a tienda externa |
| `external_store` | enum | shein / aliexpress / amazon / zara / custom |
| `external_url` | string | URL canónica del producto en la tienda |
| `affiliate_tag` | string | Tag de afiliado inyectado al redirigir |
| `cost_price` | decimal | Coste al que MODA adquiere/canaliza (para el margen) |
| `commission_rate` | decimal | % o importe fijo que la tienda paga a MODA |
| `commission_type` | enum | percent / fixed |

### Flujo de compra

1. El usuario navega el catálogo; los productos dropshipping se muestran con
   badge de tienda en la home, el checkout y la ficha.
2. Al confirmar el pedido, se crea un `Order` con `margin_total` y
   `commission_total` calculados por item (ingreso interno de MODA).
3. **No se cobra** la parte dropshipping en nuestra caja (ni se decrementa
   stock local): se paga en la tienda original.
4. La página de éxito muestra un botón **"Comprar en {Tienda} →"** por cada
   item dropshipping, que pasa por `/ir/{product}` (punto único de salida).
5. `AffiliateRedirectController` valida que el dominio destino esté en una
   **whitelist** (anti open-redirect), registra el clic en `affiliate_clicks`
   para auditoría de comisiones y hace un 302 a la tienda con el tag.

### Ingreso por pedido (campos en `orders`)

- `margin_total`: Σ (precio venta − cost_price) × qty, sólo items dropshipping.
- `commission_total`: Σ comisión estimada por item.

---

## 2. Registro de usuario con método de pago y checkout pre-poblado

- El **registro** ahora captura (opcionales): teléfono, dirección de envío
  preferida y método de pago preferido (tarjeta / PayPal / billetera / contra
  reembolso).
- La billetera se inicia en 0; el saldo se mueve **sólo** vía
  `User::applyWalletMovement()` dentro de una transacción DB con
  `lockForUpdate` (evita race conditions y saldo negativo).
- El **checkout** pre-puebla nombre, email, teléfono y dirección desde el
  perfil del usuario; persiste la dirección si el usuario no la tenía.
- Selector de pago: métodos guardados del usuario (info) + 4 métodos
  generales. Si elige billetera, se valida saldo y se debita la parte
  cobrable (productos propios + envío; la parte dropshipping se paga fuera).
- Cada endpoint de checkout/pedidos valida que el usuario sea propietario del
  pedido o admin (anti IDOR, ya existente en `success`, mantenido).

---

## 3. Métodos de pago guardados

- Tabla `payment_methods`: type, provider, **last4 (sólo 4 dígitos)**, token
  opaco, email (PayPal), expires_at (MM/YY), is_default.
- **Nunca** se almacena el PAN completo ni el CVV. El `token` está en
  `$hidden` del modelo (no se serializa).
- CRUD desde el perfil (`PaymentMethodController`); autorización vía
  `PaymentMethodPolicy` (un usuario sólo toca sus propios métodos).
- `PaymentMethod::getIsExpiredAttribute()` detecta tarjetas caducadas.

---

## 4. Billetera virtual

- `users.wallet_balance` + tabla `wallet_transactions` (ledger append-only,
  inmutable). El saldo se reconstruye desde el ledger → auditable.
- Tipos: `promo_credit`, `cashback`, `refund`, `purchase`, `adjustment`.
- Vista en el perfil con histórico de los últimos 20 movimientos.
- En el checkout, debita con `lockForUpdate` dentro de la transacción del
  pedido.

---

## 5. Eliminación de cuenta conforme a RGPD (derecho al olvido)

`AccountDeletionService` hace un borrado **físico en cascada** dentro de una
transacción DB:

| Dato | Acción |
| --- | --- |
| `payment_methods` | borrado físico (tokens de pago) |
| `wallet_transactions` | borrado físico (ledger de billetera) |
| `affiliate_clicks` | borrado físico (vínculo con persona) |
| `contact_messages` | borrado físico (PII) |
| `sessions` | borrado (logout forzado en todos los dispositivos) |
| `password_reset_tokens` | borrado |
| `personal_access_tokens` (Sanctum) | borrado si la tabla existe |
| `orders` | **anonimización**: nombre→"Usuario eliminado", email/tel→NULL, dirección→"Datos eliminados (RGPD)". Se conservan totales/items por obligación contable/fiscal. |
| `users` | `forceDelete()` físico |

- Requiere contraseña + confirmación tipada ("ELIMINAR").
- Cada borrado se registra en el log de auditoría (`config('audit.log_channel')`)
  con user_id, email, IP y conteos.
- La `orders.user_id` es `nullOnDelete`, así que al borrar el usuario los
  pedidos quedan huérfanos pero con PII anonizados.

---

## 6. Novedades / promociones / tendencias en la home

- Tabla `news_items` (type: trend/promo/news/lookbook, cover_image, tags,
  source_url, source_name, is_featured, published_at).
- **Seeder curado** (`NewsItemSeeder`) con 8 items de ejemplo.
- **Comando `scrape:news`**: lee feeds RSS curados de moda (configurables en
  `config/news.php`), cachea 1h por feed, dedup por source_url/título, maneja
  errores por feed sin abortar el resto. Respeta ToS (sólo RSS público, no
  scraping HTML arbitrario).
- La home muestra un **carrusel destacado** + **grid** de feed, enlazando al
  medio fuente. Página dedicada `/novedades` con listado paginado.

Cron sugerido: `0 * * * * php artisan scrape:news` (cada hora).

---

## 7. Footer con funcionalidad real

Los links del footer ahora apuntan a páginas con contenido a medida:

- **Envíos** (`/envios`): plazos, costes, distinción productos propios vs
  tiendas asociadas, seguimiento.
- **Devoluciones** (`/devoluciones`): derecho de desistimiento 30 días,
  política por tipo de producto, devoluciones de tiendas asociadas.
- **Contacto** (`/contacto`): formulario con validación, honeypot anti-spam,
  rate-limiting `throttle:web`, persistencia en `contact_messages` y
  notificación por email.
- **Privacidad (RGPD)** (`/privacidad`): política completa adaptada a los datos
  tratados (cuenta, pagos, billetera, pedidos, contacto, afiliación).
- **Términos** (`/terminos`): incluye el modelo dropshipping+afiliación.
- **Sobre nosotros** (`/sobre-nosotros`), **Novedades** (`/novedades`).

---

## 8. Seguridad y protección de datos

- **Rate limiters** `web` y `api` registrados en `AppServiceProvider` (Laravel
  11+ ya no los registra por defecto; sin esto, `throttle:web` lanzaba
  `MissingRateLimiterException`).
- **Anti IDOR**: `PaymentMethodPolicy` + validación de propiedad en checkout
  (`saved_payment_method_id` debe pertenecer al usuario) y en `checkout.success`
  (sólo propietario o admin).
- **Anti open-redirect**: `AffiliateRedirectController` valida el host destino
  contra una whitelist antes de redirigir.
- **Pago**: nunca PAN/CVV; sólo last4 + token opaco en `$hidden`.
- **Contraseñas**: hashing (bcrypt/argon2 vía `Password::defaults()`).
- **Sanitización**: mensajes de contacto se guardan con `e(strip_tags(...))`
  para evitar XSS persistente en el panel.
- **Auditoría**: log dedicado de borrados de cuenta (`config/audit.php`).
- **Cabeceras de seguridad**: `SecurityHeaders` middleware (HSTS,
  X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy)
  ya presentes.
- **Rel="nofollow sponsored"** en los enlaces de afiliado (cumplimiento SEO y
  transparencia de publicidad).

---

## 9. Cómo desplegar esta iteración

```bash
# 1. Copiar los ficheros del ZIP sobre el proyecto existente.
# 2. Instalar dependencias (sin cambios en composer.json, pero por si acaso):
composer install --no-dev --optimize-autoloader

# 3. Migraciones (aditivas, no destructivas):
php artisan migrate

# 4. Limpiar caches de código anterior:
php artisan optimize:clear

# 5. (Opcional) re-seed para tener productos dropshipping y novedades:
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=NewsItemSeeder
#   o un refresh completo: php artisan migrate:fresh --seed

# 6. Publicar config (opcional, ya están en el repo):
#    php artisan config:cache   # sólo en producción

# 7. Programar el scraper (cron):
#    0 * * * * cd /ruta && php artisan scrape:news >> /dev/null 2>&1
```

Variables de entorno recomendadas (en `.env`):

```
AUDIT_LOG_CHANNEL=stack      # en prod: definir canal 'audit' en logging.php
DPO_EMAIL=dpo@tudominio.com
MAIL_SUPPORT_ADDRESS=soporte@tudominio.com
```

---

## 10. Pendiente para producción real (no bloqueante en pre-prod)

- **Pasarela de pago real**: integrar Stripe/Braintree para tokenizar tarjetas
  en su iframe; sustituir el `token` demo por el real devuelto por la pasarela.
- **Programas de afiliación reales**: registrar en cada plataforma (Shein
  Affiliates, AliExpress Portals, Amazon Associates) y reemplazar
  `affiliate_tag='modaaffil-21'` por los IDs reales.
- **Cashback automático**: hoy el seeder/documentación menciona 5% cashback;
  implementar el job que, tras confirmar una conversión de afiliado (vía
  callback/postback de la plataforma), acredite la billetera.
- **Panel admin de mensajes de contacto** y **dashboard de comisiones** (sobre
  `affiliate_clicks` + `orders.margin_total/commission_total`).
- **Logging dedicado**: definir canal `audit` en `config/logging.php` con
  archivo separado y rotación larga para cumplimiento LOPDGDD.
- **Backups** de la base de datos y **rotación** de logs.

---

## Resumen de ficheros creados/modificados

**Migraciones (8 nuevas):** perfil de cliente en users, payment_methods,
wallet_transactions, dropshipping en products, extensión de orders,
contact_messages, news_items, affiliate_clicks.

**Modelos (5 nuevos + 2 editados):** PaymentMethod, WalletTransaction,
ContactMessage, NewsItem, AffiliateClick, Policy de PaymentMethod; User y
Product y Order ampliados.

**Servicios:** AccountDeletionService (GDPR).

**Controladores (4 nuevos + 3 editados):** PaymentMethodController,
AffiliateRedirectController, PageController, ContactController; RegisteredUser,
Checkout, Profile, HomeController editados.

**Comando:** `scrape:news`.

**Config:** `news.php`, `audit.php`.

**Vistas:** register, checkout/index + success, profile/edit + 3 partials
(payment-methods, wallet, delete-user-form), home, footer en layout, 7 páginas
(envios/devoluciones/contacto/privacidad/terminos/nosotros/novedades).

**Seeders:** ProductSeeder ampliado con dropshipping, NewsItemSeeder nuevo.
