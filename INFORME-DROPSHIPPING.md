# INFORME — Conversión a E-commerce Dropshipping

Fecha: 2026-11-21 · Laravel 13.43 · PHP 8.4

Esta iteración convierte MODA en un e-commerce dropshipping real con
persistencia que sobrevive a reinicios/caídas, libro de cuentas pendientes,
secretos de usuario cifrados y aislamiento estricto de datos entre usuarios.

---

## 1. Carrito persistente en BD (sobrevive a reinicios)

Antes el carrito vivía sólo en sesión y se perdía al cerrar sesión o al
reiniciar/caer el servidor. Ahora:

- **Usuario autenticado**: el carrito se persiste en `carts` + `cart_items`
  (una fila de carrito por usuario, items acumulados por producto). Sobrevive
  a reinicios, caídas y cierre de sesión: el usuario vuelve y su carrito sigue
  ahí. Es el "cache durable" que pide el modelo dropshipping.
- **Invitado**: sigue en sesión (no hay user_id). Al iniciar sesión/registrarse,
  el listener `RecordSessionStart` migra el carrito de sesión al carrito DB
  (`CartService::migrateSessionToDatabase()`).

El `CartService` mantiene la **misma interfaz pública** (`content/add/update/
remove/clear/count/subtotal/shipping/total/isEmpty/sync`), así que
`CartController` y `CheckoutController` no cambiaron.

**Aislamiento**: el `CartService` filtra siempre por `auth()->id()`; además
`CartPolicy` bloquea el acceso a carritos ajenos (incluso al admin, que no
tiene razón operativa para ver carritos previos a compra).

---

## 2. Tracking de sesión y tiempo transcurrido

- `users.session_started_at` + `users.last_login_at` se actualizan en cada
  `Illuminate\Auth\Events\Login` vía el listener `RecordSessionStart`.
- `User::is_online` deriva de la tabla `sessions` (`last_activity` en los
  últimos 5 min) — consulta filtrada por `user_id`, no revela datos de otros.
- `User::session_elapsed_for_humans` → "1h 23m" / "12m" / "45s".
- Visible en el perfil (`profile/partials/session-info.blade.php`): sesión
  activa, tiempo transcurrido y último login.

---

## 3. Libro de cuentas pendientes (settlements)

Tabla `settlements` + `SettlementService`. Por cada pedido se generan asientos
atómicos (dentro de la transacción del checkout):

| Tipo | Significado | Estado inicial |
| --- | --- | --- |
| `receivable_from_customer` | Lo que el cliente nos debe (productos propios + envío) | `settled` si pagó con billetera; si no, `pending` |
| `commission_owed_by_store` | Comisión que la tienda externa nos debe por canalizar la venta dropshipping | `pending` (hasta postback de la tienda) |
| `payable_to_supplier` | Lo que debemos al proveedor de productos propios (si tienen `cost_price`) | `pending` |

El admin gestiona las liquidaciones desde `/admin/settlements` (filtrar por
tipo/estado, marcar comisiones como liquidadas con referencia externa). El
dashboard admin muestra agregados: por cobrar / comisión por cobrar / por
pagar / margen realizado / comisión realizada.

**No son datos personales**: son obligaciones financieras. Al borrar una
cuenta, los asientos se **anonimizan** (`user_id → null`, sobreviven vinculados
al pedido) para mantener la traza contable/fiscal.

---

## 4. Secretos de usuario cifrados

- `payment_methods.token` usa el cast `encrypted` → se almacena **cifrado en
  reposo** con la `APP_KEY`. Un admin que consulte la tabla directamente ve
  ciphertext, no el token. Sólo la app lo descifra al usarlo.
- `password` sigue hasheada (bcrypt/argon2), nunca en claro.
- `token` sigue en `$hidden` del modelo → nunca se serializa a JSON/vista.
- La billetera (`wallet_balance`) se mueve sólo vía `applyWalletMovement()`
  dentro de transacción con `lockForUpdate` (ledger inmutable en
  `wallet_transactions`).

---

## 5. Caché de catálogo externo (sobrevive a reinicios)

Tabla `external_product_cache` + comando `cache:external-products`. Snapshot
por par `(external_store, external_product_id)` con `cached_at` y `expires_at`.

- En pre-producción siembra el caché desde los productos dropshipping de la BD.
- En producción, sustituir el bloque de seeding por llamadas a las APIs de cada
  tienda (Shein Affiliates, AliExpress Portals, Amazon Product Advertising).
- Mientras el caché no caduque, el catálogo se sirve desde BD aunque la API
  externa esté caída o el servidor se haya reiniciado (TTL configurable vía
  `DROPSHIP_CACHE_TTL`).

Cron sugerido: `*/15 * * * * php artisan cache:external-products`.

---

## 6. Aislamiento estricto de datos entre usuarios

Cada recurso de usuario se scopea a su dueño mediante policies:

| Recurso | ¿Quién ve? | ¿Admin? |
| --- | --- | --- |
| Pedidos (`Order`) | Propietario | Sí (operativo) |
| Métodos de pago (`PaymentMethod`) | Propietario | **No** (tokens) |
| Billetera (`WalletTransaction`) | Propietario | **No** (sólo agregados en dashboard) |
| Carrito (`Cart`) | Propietario | **No** (estado pre-compra) |
| Mensajes de contacto | Propietario | Sí (responder) |
| Clics de afiliación | Propietario | Sí (analytics) |
| Liquidaciones (`Settlement`) | — | Sí (sólo admin; financieros) |

Policies nuevas: `CartPolicy`, `WalletTransactionPolicy`,
`ContactMessagePolicy`, `AffiliateClickPolicy`, `SettlementPolicy`.

### Qué ve el admin y qué NO

**El admin SÍ ve** (datos operativos, sin secretos):
- Pedidos: estado, totales, items, datos de envío (necesarios para fulfillment).
- Liquidaciones: flujo de caja pendiente y realizado (agregado + por pedido).
- Clics de afiliación: analytics de comisiones (IP/sesión + producto).
- Mensajes de contacto: contenido (el usuario los envió voluntariamente).
- Agregados de dashboard: ingresos, clientes, productos, margen, comisión.

**El admin NO ve** (secretos / datos sensibles individuales):
- Contraseñas (hasheadas, en `$hidden`).
- Tokens de pago (cifrados en reposo + `$hidden`).
- Ledger individual de billeteras de usuarios (sólo agregados).
- Carritos ajenos (no hay razón operativa).
- Sesiones individuales de otros usuarios.

---

## 7. Eliminación de cuenta (RGPD) ampliada

`AccountDeletionService` ahora también:
- Borra `carts` + `cart_items` del usuario (estado pre-compra, sin valor
  contable).
- **Anonimiza** `settlements` (`user_id → null`): sobreviven vinculados al
  pedido para la traza contable/fiscal, igual que los pedidos (cuyos PII ya se
  anonimizaban).
- El log de auditoría registra los nuevos conteos (`cart_deleted`,
  `settlements_anonymized`).

---

## 8. Datos que la BD almacena (resumen del pedido del usuario)

| Dato | Tabla | Sobrevive reinicios |
| --- | --- | --- |
| password (hash) | users | ✅ |
| email | users | ✅ |
| sesión iniciada / tiempo transcurrido | users.session_started_at + sessions | ✅ |
| productos en carrito | carts + cart_items | ✅ (antes se perdía) |
| cuentas pendientes por pagar/cobrar | settlements | ✅ |
| secretos (token de pago) | payment_methods (cifrado) | ✅ |
| billetera + ledger | users.wallet_balance + wallet_transactions | ✅ |
| catálogo externo cacheado | external_product_cache | ✅ (sobrevive caídas) |

---

## 9. Despliegue

```bash
# 1. Copiar el ZIP sobre el proyecto.
# 2. Migraciones (aditivas):
php artisan migrate

# 3. Limpiar caches:
php artisan optimize:clear

# 4. Sembrar caché externo:
php artisan cache:external-products

# 5. Cron:
#  */15 * * * * cd /ruta && php artisan cache:external-products
#  0 * * * *   cd /ruta && php artisan scrape:news
```

Variables de entorno:
```
DROPSHIP_CACHE_TTL=60        # minutos de validez del caché externo
AUDIT_LOG_CHANNEL=stack      # en prod: canal 'audit' dedicado
```

---

## 10. Pendiente para producción

- **APIs de tiendas reales**: sustituir el seeding de `cache:external-products`
  por llamadas a las APIs oficiales (Shein/AliExpress/Amazon) con credenciales.
- **Postback de conversión**: endpoint que las tiendas llamen al confirmar una
  venta dropshipping → `SettlementService::settleCommission()` automático.
- **Pasarela de pago real**: integrar Stripe/Braintree para tokenizar tarjetas
  (el campo `token` ya está cifrado y oculto; sólo falta la integración).
- **Auditoría de admin**: log de qué admin vio qué pedido/settlement (traza
  interna de acceso a datos de clientes).
- **Backups** cifrados de la BD (los tokens cifrados dependen de la APP_KEY:
  robar la APP_KEY + la BD expone los tokens; proteger ambos).

---

## Resumen de ficheros de esta iteración

**Migraciones (4):** carts+cart_items, settlements, external_product_cache,
session tracking (users). + ajuste token→varchar(255) y settlements.user_id→
nullOnDelete.

**Modelos (4 nuevos):** Cart, CartItem, Settlement, ExternalProductCache.
**Editados:** User (cart/settlements/session helpers), PaymentMethod (token
cifrado).

**Servicios:** SettlementService. **Editados:** CartService (DB-backed),
AccountDeletionService (carts + settlements).

**Controladores:** Admin\SettlementController. **Editados:** CheckoutController
(asientos), Admin\DashboardController (finanzas).

**Policies (5 nuevas):** Cart, WalletTransaction, ContactMessage,
AffiliateClick, Settlement.

**Listener:** RecordSessionStart (+ registro en AppServiceProvider).

**Comando:** cache:external-products. **Config:** dropship.php.

**Vistas:** admin/settlements/index, profile/partials/session-info,
admin/dashboard (bloque finanzas), admin nav (link Liquidaciones),
profile/edit (sección sesión).
