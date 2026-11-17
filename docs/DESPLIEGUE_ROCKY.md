# Despliegue en Rocky Linux 9.8

Este documento describe los pasos de la aplicación. La instalación y configuración del sistema operativo, PHP-FPM, Nginx, Git, Composer, Node.js y las reglas de red se realizan por separado y deben respetar la política de SELinux del laboratorio.

## 1. Preparar el código en cada Page

En Page1 y Page2, clona la misma rama/commit en el mismo directorio:

```bash
cd /var/www
sudo git clone https://github.com/USUARIO/REPOSITORIO.git laboratorio
cd /var/www/laboratorio
```

En actualizaciones, usa `git pull` para llevar ambas Pages al mismo commit. No mantengas cambios de aplicación exclusivos en una Page.

## 2. Dependencias y assets

Instala dependencias PHP optimizadas para producción y compila los assets:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

El directorio `public/build` es generado y no se versiona. Debe existir en cada Page antes de atender tráfico. Puede compilarse en ambas máquinas con la misma versión de Node o distribuirse el mismo resultado del build.

## 3. Variables de entorno

En cada Page crea el archivo local a partir del ejemplo y establece `APP_ENV=production` y `APP_DEBUG=false`:

```bash
cp .env.example .env
```

Configura en ambas Pages los mismos valores para:

```dotenv
APP_KEY=<la misma clave secreta en ambas Pages>
APP_ENV=production
APP_DEBUG=false
APP_URL=<URL o dirección con la que Windows accede al LoadBalancer>

DB_CONNECTION=mariadb
DB_HOST=192.168.184.50
DB_PORT=6969
DB_DATABASE=laboratorio
DB_USERNAME=laravel_app
DB_PASSWORD=<contraseña configurada en MariaDB>

SESSION_DRIVER=database
SESSION_COOKIE=laboratorio_session
SESSION_DOMAIN=null
SESSION_PATH=/
```

Genera la clave una sola vez en una instalación, guarda el secreto fuera de Git y configura el mismo `APP_KEY` en Page1 y Page2. No ejecutes `key:generate` independientemente en cada backend. Si el usuario accede mediante un nombre DNS, configura en ambas Pages el mismo `SESSION_DOMAIN` adecuado a ese hostname; con el host-only predeterminado (`null`) ambas reciben la cookie porque el navegador siempre se conecta al mismo balanceador.

La única variable funcional distinta entre las Pages es:

```dotenv
# Page1 (192.168.184.30)
LAB_SERVER_LABEL=Servidor1

# Page2 (192.168.184.40)
LAB_SERVER_LABEL=Servidor2
```

La contraseña y la clave son secretos locales: no las incluyas en Git ni en documentación.

## 4. Migrar la base central

Ejecuta la migración una sola vez, desde Page1 o Page2, después de configurar su `.env`:

```bash
php artisan migrate --force
```

Las migraciones originales crean `users`, `password_reset_tokens` y `sessions`. La migración adicional crea `login_audits`. No repitas la migración desde ambas Pages en paralelo.

## 5. Permisos Laravel

Ajusta propietario y permisos de las carpetas escribibles para el usuario real de PHP-FPM (comprueba si es `nginx` en tu instalación):

```bash
sudo chown -R nginx:nginx storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

No hagas escribible todo el repositorio. Mantén SELinux habilitado; si deniega escritura, revisa los contextos y aplica los tipos persistentes apropiados a `storage` y `bootstrap/cache` en lugar de desactivarlo.

## 6. Nginx y PHP-FPM en cada Page

Configura el `root` de Nginx hacia `/var/www/laboratorio/public`. Ejemplo de bloques esenciales para el servidor virtual:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/laboratorio/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTP_X_FORWARDED_FOR $http_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;
        fastcgi_pass unix:/run/php-fpm/www.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Adapta el socket de PHP-FPM al de tu instalación y valida la configuración antes de recargar Nginx. Las reglas de firewall y SELinux se documentan/administran en el sistema, no desde este repositorio.

En el LoadBalancer, Nginx debe enviar las cabeceras reenviadas al backend y permitir solamente el tráfico previsto:

```nginx
upstream laboratorio_pages {
    least_conn;
    server 192.168.184.30:80;
    server 192.168.184.40:80;
}

server {
    listen <interfaz-y-puerto-de-acceso>;

    location / {
        proxy_pass http://laboratorio_pages;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Laravel confía en la IP privada del LoadBalancer (`192.168.184.20`) declarada en `bootstrap/app.php`. Confirma que esa sea la IP de origen de las conexiones a Page1/Page2. Si el tráfico sale por otra interfaz, ajusta la lista al origen exacto; nunca confíes indiscriminadamente en `X-Forwarded-For`.

## 7. Optimización y verificación

Después de configurar `.env` y los permisos, en cada Page:

```bash
php artisan config:cache
php artisan route:cache
```

Comprueba desde Windows que el dashboard muestra `Servidor1` o `Servidor2` según la Page atendida y que los inicios de sesión se conservan al alternar servidores. Revisa en MariaDB que sesiones y auditorías se comparten y que las IP registradas corresponden al cliente resuelto mediante el proxy confiable.

Si cambia cualquier configuración almacenada en caché, vuelve a ejecutar `php artisan config:cache` en las dos Pages. Para limpiar esa caché:

```bash
php artisan config:clear
```
