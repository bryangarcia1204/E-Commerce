# Guía de despliegue — MODA E-Commerce

Runbook paso a paso para poner la tienda en producción. Aplica a un servidor
Linux con PHP 8.3+, Nginx o Apache, MySQL/MariaDB y Composer + Node instalados.

---

## 1. Requisitos del servidor

| Recurso         | Versión mínima | Recomendado                |
|-----------------|----------------|----------------------------|
| PHP             | 8.3            | 8.3.x con extensiones: `pdo_mysql`, `mbstring`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd`/`imagick`, `openssl`, `zip` |
| Base de datos   | MySQL 8 / MariaDB 10.6 | MySQL 8.4            |
| Servidor web    | Nginx 1.24 / Apache 2.4 + mod_rewrite | Nginx + PHP-FPM |
| Composer        | 2.7             | 2.7+                      |
| Node            | 20 LTS          | 22 LTS                    |
| (Opcional) Redis| 7.x             | Para cache y sesiones    |

---

## 2. Preparar el código en el servidor

```bash
# Clonar el repo (o subir el ZIP descomprimido) a /var/www/moda
git clone <tu-repo> /var/www/moda
cd /var/www/moda

# Permisos: el usuario del servidor web (www-data / nginx) debe poder
# escribir en storage/ y bootstrap/cache/.
sudo chown -R $USER:www-data /var/www/moda
sudo find /var/www/moda -type f -exec chmod 664 {} \;
sudo find /var/www/moda -type d -exec chmod 775 {} \;
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

---

## 3. Configurar el entorno

```bash
cp .env.example .env

# Generar la APP_KEY (obligatorio; sin ella, sesiones y cookies fallan).
php artisan key:generate

# Editar .env con los valores reales:
#   APP_NAME, APP_URL, DB_*, MAIL_*, SANCTUM_STATEFUL_DOMAINS, ADMIN_*
#   Importante: APP_ENV=production, APP_DEBUG=false
$EDITOR .env
```

**Checklist obligatorio de `.env` en producción:**

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://tudominio.com` (con HTTPS)
- [ ] `APP_KEY=` rellena (generada en el paso anterior)
- [ ] `DB_CONNECTION=mysql` y credenciales reales
- [ ] `CACHE_STORE=database` (o `redis`)
- [ ] `SESSION_DRIVER=database` (o `redis`)
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `QUEUE_CONNECTION=database` (para emails asíncronos)
- [ ] `MAIL_*` con un SMTP real (Mailgun, SES, Sendgrid, etc.)
- [ ] `SANCTUM_STATEFUL_DOMAINS=tudominio.com`
- [ ] `ADMIN_PASSWORD=` con una contraseña fuerte (o vacío para autogenerada)

---

## 4. Instalar dependencias y construir assets

```bash
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts
npm run build
php artisan storage:link
```

El comando `storage:link` crea el symlink `public/storage → storage/app/public`,
sin el cual las imágenes subidas desde el admin no se ven.

> Atajo: `composer deploy` ejecuta todos los pasos de producción de una vez.

---

## 5. Migraciones y seed

```bash
php artisan migrate --force
```

La iteración de 2026-11-25 añade dos migraciones:
`fix_affiliate_clicks_columns` (fix del registro de clics de afiliación)
y `create_ads_table` (sistema de anuncios). No requieren datos previos.

Para crear el usuario admin:

```bash
php artisan db:seed --class=AdminUserSeeder
# Si ADMIN_PASSWORD estaba vacío en .env, el comando imprime una contraseña
# aleatoria por consola. Anótala y cámbiala desde /admin tras el primer login.
```

**No ejecutes `db:seed --class=ProductSeeder` en producción** salvo que
quieras datos demo. Esos seeders son para desarrollo.

**Anuncios (ads):** el sistema viene desactivado por seguridad vía
`ADS_ENABLED=false`. Para activarlo:

```bash
# .env
ADS_ENABLED=true
php artisan db:seed --class=AdSeeder   # banners de ejemplo en posiciones
php artisan optimize:clear             # refresca caché de config/vistas
# Gestionarlos después en /admin/anuncios
```

**Pasarela de pago (tarjeta):** sin `STRIPE_SECRET` el checkout usa el
SandboxGateway (sin cargo real; tarjetas de test de Stripe: 4242… éxito,
4000…0002 rechazada, 4000…9995 sin fondos). Con claves reales se usa la
API REST de Stripe automáticamente. Registra también el webhook
`https://tudominio.com/webhooks/stripe` en el panel de Stripe con el
evento `payment_intent.succeeded` (y `payment_intent.payment_failed`) y
copia su secreto a `STRIPE_WEBHOOK_SECRET`.

---

## 6. Cache de configuración, rutas y vistas

En producción se deben cacheificar para ganar rendimiento:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> Tras cualquier cambio en `.env` o en archivos de configuración, ejecuta
> `php artisan config:cache` de nuevo. `config:clear` para revertir.

---

## 7. Nginx (configuración de ejemplo)

```nginx
server {
    listen 443 ssl http2;
    server_name tudominio.com;

    root /var/www/moda/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tudominio.com/privkey.pem;

    # Compresión
    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;

    # Logs
    access_log /var/log/nginx/moda-access.log;
    error_log  /var/log/nginx/moda-error.log;

    # Impedir acceso a archivos sensibles
    location ~ /\.(?!well-known).* { deny all; }
    location ~ ^/(vendor|node_modules|storage/framework|database)/ { deny all; }

    # Imágenes subidas: servirlas vía el symlink public/storage
    location /storage/ {
        alias /var/www/moda/storage/app/public/;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Assets con hash de Vite: cache largo
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # PHP: pasar a PHP-FPM
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Redirección HTTP -> HTTPS
server {
    listen 80;
    server_name tudominio.com;
    return 301 https://$host$request_uri;
}
```

---

## 8. Workers de cola y scheduler

Los emails de confirmación de pedido se encolan. Lanza un worker
(recomendado con Supervisor):

```ini
# /etc/supervisor/conf.d/moda-worker.conf
[program:moda-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/moda/artisan queue:work --sleep=3 --tries=3 --max-time=60
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/moda/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start moda-worker:*
```

Si más adelante añades tareas programadas (limpieza de carritos, sincronización
de stock, etc.), añade al crontab del usuario web:

```cron
* * * * * cd /var/www/moda && php artisan schedule:run >> /dev/null 2>&1
```

---

## 9. Health check

El endpoint `GET /up` devuelve 200 si la app y la BD responden. Úsalo en
el balanceador / uptime monitor.

---

## 10. Backups

```bash
# BD
mysqldump --single-transaction -u moda_user -p moda | gzip > /backups/moda-$(date +%F).sql.gz

# Imágenes subidas
tar czf /backups/moda-storage-$(date +%F).tar.gz -C /var/www/moda storage/app/public
```

Programa ambos en cron diario; retén al menos 7 días.

---

## 11. Tras cada deploy

```bash
cd /var/www/moda
git pull
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart   # reinicia los workers para que carguen código nuevo
sudo supervisorctl restart moda-worker:*
```

---

## 12. Puesta en mantenimiento

```bash
php artisan down --secret="UnaClaveAleatoria"   # acceso por ?secret=...
php artisan up
```

Con `--secret`, puedes seguir tú accediendo vía `https://tudominio.com/?secret=UnaClaveAleatoria`
mientras el resto ve la página de mantenimiento.
