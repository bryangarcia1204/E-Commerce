# MODA — Tienda online (Laravel + Breeze + Tailwind)

E-commerce de moda con catálogo, carrito, checkout, panel de administración
y API REST ligera. Construido sobre Laravel 11+ con Breeze (auth), Tailwind
CSS 4 y Alpine.js.

## Características

- **Catálogo público**: home con destacados, novedades y ofertas; listado con
  búsqueda, filtro por categoría y ordenación; ficha de producto con galería.
- **Carrito en sesión**: añadir, actualizar cantidad, eliminar, vaciar. Envío
  gratis a partir de 50 €.
- **Checkout**: datos de envío validados, descuento de stock atómico en
  transacción, email de confirmación encolaado, página de éxito.
- **Historial de pedidos**: `/mis-pedidos` para clientes.
- **Panel admin**: dashboard con métricas, gestión CRUD de productos (con
  imágenes subidas al disco `public`), gestión de pedidos (listado + detalle
  + cambio de estado). Acceso restringido por rol + `ProductPolicy`/
  `OrderPolicy`.
- **API REST**: `/api/products`, `/api/products/{slug}`, `/api/user`,
  `/api/admin/stats` con throttle.
- **Seguridad**: cabeceras HSTS/X-Frame/Referrer/Permissions-Policy, CSRF en
  todas las escrituras, rate-limit en login y API, HTTPS forzado en
  producción, mass-assignment del rol protegido, IDOR cerrado en pedidos.

## Requisitos

- PHP 8.3+ (extensiones habituales de Laravel + `gd`/`imagick`)
- MySQL 8 / MariaDB 10.6+ (o SQLite para desarrollo)
- Composer 2.7+, Node 20+
- (Opcional) Redis para cache/sesiones/cola

## Puesta en marcha (desarrollo)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan storage:link

# Base de datos: en .env usa sqlite para desarrollo local
php artisan migrate
php artisan db:seed           # crea admin + categorías + productos demo

npm install
npm run dev                   # o: php artisan serve + npm run dev
```

Accede a `http://localhost:8000`. Para entrar al admin (`/admin`), usa la
cuenta creada por `AdminUserSeeder` (ver consola al ejecutar el seeder).

## Puesta en producción

Lee la guía completa: [`DEPLOY.md`](./DEPLOY.md). Resumen:

```bash
composer deploy               # instala, compila assets, cachea config/rutas/vistas
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder
```

## Estructura destacada

```
app/
  Http/Controllers/
    HomeController, ProductController, CartController, CheckoutController,
    OrderController                      # historial del cliente
    Admin/DashboardController, ProductController, OrderController
  Models/  Product (SoftDeletes), Order (STATUS_*), OrderItem, Category, User
  Services/CartService                   # carrito en sesión + sync desde BD
  Notifications/OrderConfirmation        # email encolaado tras checkout
  Policies/ ProductPolicy, OrderPolicy
  Http/Middleware/SecurityHeaders        # HSTS, X-Frame-Options, etc.
database/migrations/                     # esquema + 3 migraciones de saneamiento
routes/  web.php, api.php, auth.php
resources/views/                         # Blade (storefront + admin + auth)
public/img/placeholder.svg               # fallback local de imagen
```

## Tests

```bash
composer test        # = php artisan test (Pest)
```

## Notas

- Las imágenes de producto se guardan en el disco `public` y se sirven vía el
  symlink `public/storage`. **Obligatorio** ejecutar `php artisan storage:link`.
- Si no hay imagen, se muestra `public/img/placeholder.svg` (sin llamadas a
  servicios externos como picsum.photos).
- El checkout es una demo: no integra una pasarela de pago real. El pedido
  queda en estado `pending` hasta que un admin lo pase a `paid`/`shipped`/
  `delivered`. Para integrar Stripe/Redsys/MercadoPago, añade las claves en
  `.env` y sustituye el flujo de `CheckoutController::store`.
