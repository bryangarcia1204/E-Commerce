# INFORME — Capa Redis para peticiones muy seguidas

Fecha: 2026-11-21 · Laravel 13 · PHP 8.4

Esta iteración añade **Redis como capa de caché para las peticiones muy
frecuentes** del e-commerce dropshipping: catálogo, detalle de producto,
home, conteo del carrito (se pinta en cada página), feed de novedades y
agregados del panel admin. Más rate limiting anti-flood respaldado por Redis.

---

## ⚠️ Errata y fix — "incomplete object" al deserializar Collections

La versión inicial de esta capa cacheaba **Colecciones/Modelos Eloquent**
directamente (`Product::...->get()`, paginadores). Al deserializar el blob
en un cache HIT, PHP devolvía un `__PHP_Incomplete_Class` de
`Illuminate\Database\Eloquent\Collection` y la vista fallaba con:

> The script tried to call a method on an incomplete object…
> home.blade.php:29 — `$featured->firstWhere(...)`

Causa de fondo: serializar objetos Eloquent en la caché es frágil (la clase
no siempre está resuelta al `unserialize`, y `SerializesModels` descarta las
relaciones eager-loaded → N+1 latente). Además, las tarjetas de producto y el
formulario del detalle llevan `@csrf` (token de sesión); congelar ese token en
HTML cacheado provocaba **419** a todos los demás usuarios.

**Fix aplicado (cachear HTML de parciales, no objetos):**

1. Cada hot path ahora renderiza su **parcial Blade a HTML** y cachea el
   *string* resultante (`View::make('partials.…')->render()`). Los strings se
   serializan/deserializan sin tocar el autoloader → cero "incomplete object".
   El layout (cabecera, carrito, auth) queda dinámico fuera de la caché.
   - `partials/home/content`, `partials/products/index-content`,
     `partials/products/show-content`, `partials/pages/novedades-content`.
2. **CSRF por placeholder**: las tarjetas y el formulario del detalle usan
   `<input name="_token" value="__CSRF_TOKEN__">` en vez de `@csrf`. El
   controlador sustituye el placeholder por `csrf_token()` real en cada
   request (`CacheService::substituteCsrf`) → el blob cacheado nunca guarda
   un token de sesión → sin 419.
3. **Dashboard**: `recentOrders` (Collection) sale de la caché; se consulta
   fresco (1 query, 5 filas). Sólo se cachean escalares (COUNT/SUM).
4. **`cache:warm`** sólo pre-calienta la **home** (catálogo/novedades tienen
   paginación cuyas URLs dependen la request HTTP real; pre-renderizarlos en
   consola daría URLs `localhost` rotas — se calientan solos en la 1ª request).
5. **Migración self-healing**: `CacheService::versionedKey` añade `:s2:` al
   esquema de clave. Las entradas viejas (formato sin `:s2:`, con Collections)
   quedan huérfanas y expiran por TTL → **no hace falta `cache:clear` manual**
   tras desplegar el fix (aunque sigue siendo buena práctica ejecutarlo).

---

## 1. Activación de Redis

Laravel 13 ya trae la infraestructura Redis configurada por defecto
(conexión `redis` en `config/database.php` con DB index separado para cache;
store `redis` en `config/cache.php`). Esta iteración:

- `.env.example`: `CACHE_STORE=redis` (las peticiones frecuentes se sirven
  desde RAM) + `REDIS_HOST/PORT/DB/CACHE_DB`.
- Requiere **ext-redis (phpredis)** o el paquete `predis/predis` con
  `REDIS_CLIENT=predis`.
- **Sesiones siguen en `database`**, no en Redis: el atributo
  `User::is_online` consulta la tabla `sessions` por `user_id`; en Redis las
  sesiones no se indexan por usuario y no se podría derivar "online".
  (Para escalar sesiones a Redis, reimplementar `is_online` con un set de
  presencia separado.)

---

## 2. CacheService — wrapper Redis con fallback graceful

`app/Services/CacheService.php`. Tres garantías clave:

1. **Fallback graceful**: si Redis cae (timeout, reinicio, conexión rota), NO
   se lanza excepción a la request. Se loguea un warning y se ejecuta el
   closure directamente (servicio desde BD). Un hot path nunca tira la
   página por un fallo de caché.

2. **Invalidación por versión (sin `KEYS` scan)**: cada namespace
   (`catalog`, `home`, `news`, `dashboard`) tiene un contador de versión en
   Redis. Las claves cacheadas incluyen la versión: `catalog:v3:<suffix>`. Al
   mutar datos, `bumpVersion()` incrementa el contador y todas las claves
   anteriores quedan huérfanas y expiran por TTL. Evita el costoso
   `KEYS prefix*`/`SCAN` que bloquea Redis en producción.

3. **TTLs configurables** en `config/dropship.php` (`hot_path_ttl`) con
   defaults sensatos (catalog/home 10 min, news 15 min, dashboard 1 min,
   cart 30 s).

Métodos: `rememberVersioned(namespace, suffix, cb)`, `remember(key, cb, ttl)`,
`forget(key)`, `bumpVersion(ns)`, `bumpVersions([ns...])`.

---

## 3. Hot paths cacheados

| Hot path | Namespace | TTL | Invalidación |
| --- | --- | --- | --- |
| Home (featured, novedades, flash, dropshipping, noticias) | `home` | 10 min | mutar productos / noticias |
| Catálogo con filtros + paginación | `catalog` | 10 min | mutar productos |
| Detalle de producto + relacionados | `catalog` | 10 min | mutar productos |
| Listado por categoría | `catalog` | 10 min | mutar productos |
| Feed de novedades (`/novedades`) | `news` | 15 min | `scrape:news` |
| **Conteo del carrito** (badge en cada página) | `cart` (por usuario) | 30 s | add/update/remove/clear |
| Agregados del dashboard admin | `dashboard` | 1 min | crear pedido / cambiar estado / liquidar comisión |

El conteo del carrito es el hot path nº1: se pinta en el badge del layout en
**cada página**. Antes cada vista hacía un `SUM(quantity)` a la BD; ahora es
un `GET` a Redis (30 s) y se invalida al mutar el carrito.

La clave del catálogo codifica `q + categoría + orden + página` (md5), así que
cada combinación de filtros tiene su entrada propia.

---

## 4. Invalidación (coherencia caché ↔ BD)

| Evento | Acción |
| --- | --- |
| Admin crea/edita/borra producto | `bumpVersions(['catalog','home'])` |
| `scrape:news` importa items nuevos | `bumpVersions(['news','home'])` |
| Checkout confirma pedido | `bumpVersion('dashboard')` |
| Admin cambia estado de pedido | `bumpVersion('dashboard')` |
| Admin liquida una comisión | `bumpVersion('dashboard')` |
| Carrito: add/update/remove/clear | `forget('cart:count:{userId}')` |

Las versiones hacen que la invalidación sea O(1) (un `INCR` en Redis) sin
escanear claves.

---

## 5. Rate limiting anti-flood respaldado por Redis

El `RateLimiter` de Laravel usa el store de caché. Con `CACHE_STORE=redis`,
los contadores de peticiones viven en RAM (no en BD). Limiters definidos en
`AppServiceProvider`:

| Limiter | Límite | Aplicación |
| --- | --- | --- |
| `web` | 60/min | carrito (escritura), contacto |
| `api` | 60/min | rutas API |
| `storefront` (nuevo) | 120/min | home, catálogo, detalle, categorías, novedades |

`throttle:storefront` protege los hot paths cacheados contra scrapers/bots
que saturen el catálogo. Clave por usuario autenticado o IP (no penaliza NAT).

---

## 6. Comando `cache:warm`

`php artisan cache:warm` pre-calienta en Redis la home, el catálogo página 1
y el feed de noticias tras un despliegue o reinicio, para que el primer
visitante no pague el cold-start.

Cron sugerido (producción):
```
*/15 * * * * php artisan cache:external-products
0 * * * *   php artisan scrape:news
@reboot     php artisan cache:warm
```

---

## 7. Variables de entorno nuevas

```
CACHE_STORE=redis
REDIS_CLIENT=phpredis        # o predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0                   # sesiones/locks (aunque sesiones van en DB)
REDIS_CACHE_DB=1             # caché aislado
CACHE_TTL_CATALOG=600        # segundos (opcional, hay defaults)
CACHE_TTL_HOME=600
CACHE_TTL_NEWS=900
CACHE_TTL_DASHBOARD=60
CACHE_TTL_CART=30
```

---

## 8. Resumen de ficheros de esta iteración

**Nuevos:**
- `app/Services/CacheService.php` — wrapper Redis + fallback + versiones.
- `app/Console/Commands/WarmCache.php` — `cache:warm`.

**Editados:**
- `.env.example` — `CACHE_STORE=redis`, `REDIS_*`, TTLs.
- `app/Providers/AppServiceProvider.php` — limiter `storefront`.
- `routes/web.php` — `throttle:storefront` en hot paths.
- `app/Http/Controllers/HomeController.php` — caché home.
- `app/Http/Controllers/ProductController.php` — caché catálogo/detalle/categoría.
- `app/Http/Controllers/PageController.php` — caché novedades.
- `app/Http/Controllers/Admin/DashboardController.php` — caché agregados.
- `app/Http/Controllers/Admin/ProductController.php` — invalidación catalog+home.
- `app/Http/Controllers/Admin/OrderController.php` — invalidación dashboard.
- `app/Http/Controllers/Admin/SettlementController.php` — invalidación dashboard.
- `app/Http/Controllers/CheckoutController.php` — invalidación dashboard.
- `app/Services/CartService.php` — caché conteo por usuario + invalidación.
- `app/Console/Commands/ScrapeNews.php` — invalidación news+home.
- `config/dropship.php` — `hot_path_ttl`.

---

## 9. Notas de despliegue

- **No pude ejecutar PHP/Redis** en este sandbox (no instalados). El código
  está revisado por consistencia (imports, rutas, limiter referenciado vs
  definido, puntos de invalidación, sin `self::TTL` suelto). Al desplegar:
  `composer require predis/predis` (si no hay ext-redis), `php artisan
  optimize:clear`, `php artisan cache:warm`.
- **Evicción Redis**: configurar `maxmemory-policy=allkeys-lru` en redis.conf
  para que bajo presión de memoria expulse primero las claves menos usadas.
- **Persistencia**: RDB + AOF para que el caché sobreviva reinicios de Redis
  (no es crítico —se regenera— pero evita cold-start masivo).
- **Monitorización**: vigilar `hit_ratio` y latencia de Redis; si el ratio
  cae, revisar invalidaciones demasiado agresivas.
