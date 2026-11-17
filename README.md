# Laboratorio Laravel 12: balanceo y MariaDB

Proyecto académico para demostrar autenticación con Laravel Livewire, sesiones compartidas en MariaDB, balanceo entre dos servidores web y auditoría de inicios de sesión.

## Arquitectura del laboratorio

| Máquina | IP Host-only | Función |
|---|---|---|
| SSH-GESTOR | `192.168.184.10` | Bastión administrativo |
| LoadBalancer | `192.168.184.20` | Nginx con `least_conn`; punto de acceso desde Windows |
| Page1 | `192.168.184.30` | Laravel 12, Nginx y PHP-FPM |
| Page2 | `192.168.184.40` | Mismo código Laravel 12, Nginx y PHP-FPM |
| Database | `192.168.184.50:6969` | MariaDB centralizada |

Page1 y Page2 usan el mismo repositorio y esquema. Solo `LAB_SERVER_LABEL` cambia para identificar cuál atendió la petición.

## Requisitos

- PHP 8.2 o superior; PHP 8.3 es la versión recomendada.
- Composer 2.
- Node.js y npm para compilar los assets de Vite.
- Git.
- Extensiones PHP de Laravel, incluyendo `pdo_mysql`, `fileinfo`, `mbstring`, `openssl` y `zip`. Para pruebas, también `pdo_sqlite` y `sqlite3`.
- Acceso de red a MariaDB en `192.168.184.50:6969` para ejecutar la aplicación y las migraciones.

Este proyecto parte del starter kit oficial:

```powershell
composer create-project laravel/livewire-starter-kit laboratorio v1.0.1
```

La restricción `laravel/framework: ^12.0` y el `composer.lock` mantienen el proyecto en Laravel 12.

## Instalación local

Desde la carpeta del proyecto:

```powershell
composer install
Copy-Item .env.example .env
```

Edita `.env`, establece la contraseña de MariaDB y asigna una clave de aplicación:

```powershell
php artisan key:generate
npm ci
npm run build
php artisan migrate
```

Para desarrollar localmente, inicia Vite y Laravel en terminales separadas:

```powershell
npm run dev
php artisan serve
```

`php artisan serve` es solo para desarrollo local; en Rocky Linux se usa Nginx con PHP-FPM. La IP `192.168.184.50` puede no ser accesible desde Windows si la red Host-only no está enrutada hacia el equipo anfitrión; no publiques MariaDB para resolverlo. Ejecuta las migraciones desde una Page o configura una base de desarrollo accesible y aislada.

## Base de datos y sesiones

La configuración de ejemplo apunta a MariaDB:

```dotenv
DB_CONNECTION=mariadb
DB_HOST=192.168.184.50
DB_PORT=6969
DB_DATABASE=laboratorio
DB_USERNAME=laravel_app
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_COOKIE=laboratorio_session
```

La migración original del starter kit crea `users`, `password_reset_tokens` y `sessions`. La migración `login_audits` añade la auditoría con relación a `users`. Ejecuta `php artisan migrate` una sola vez contra la base central; Page1 y Page2 no deben mantener esquemas ni sesiones locales distintos.

## Identidad de Page1 y Page2

```dotenv
# En Page1
LAB_SERVER_LABEL=Servidor1

# En Page2
LAB_SERVER_LABEL=Servidor2
```

El valor se lee desde `config('laboratorio.server_label')` y se muestra en el encabezado y dashboard. No se usa para identificar al usuario.

## Pruebas

Las pruebas usan SQLite en memoria y no se conectan a MariaDB:

```powershell
php artisan test
```

Consulta [docs/PRUEBAS.md](docs/PRUEBAS.md) para el alcance de las pruebas.

## GitHub

1. Crea un repositorio vacío en GitHub, sin agregar archivos iniciales.
2. Desde esta carpeta, inicializa y publica el código:

   ```powershell
   git init
   git add .
   git commit -m "Crear laboratorio Laravel 12 con balanceo"
   git branch -M main
   git remote add origin https://github.com/USUARIO/REPOSITORIO.git
   git push -u origin main
   ```

3. Antes de confirmar, comprueba `git status` y que `.env`, `vendor/`, `node_modules/` y los logs no aparezcan entre los archivos preparados.
4. Nunca subas el valor de `APP_KEY`, contraseñas ni archivos `.env`. Configura esos secretos directamente en cada VM.

## Despliegue

El procedimiento completo está en [docs/DESPLIEGUE_ROCKY.md](docs/DESPLIEGUE_ROCKY.md); el diagrama y el flujo de IP están en [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md).
