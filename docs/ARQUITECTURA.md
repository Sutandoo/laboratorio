# Arquitectura

## Flujo de peticiones

```text
Navegador Windows
       |
       | HTTP/HTTPS hacia la interfaz NAT del balanceador
       v
LoadBalancer 192.168.184.20 (Nginx, least_conn)
       |                                      |
       | X-Forwarded-For / Proto              | misma configuración
       v                                      v
Page1 192.168.184.30                    Page2 192.168.184.40
Laravel 12 + Nginx + PHP-FPM            Laravel 12 + Nginx + PHP-FPM
LAB_SERVER_LABEL=Servidor1              LAB_SERVER_LABEL=Servidor2
       \                                      /
        \                                    /
         +---- MariaDB 192.168.184.50:6969 -+
               users, sessions, login_audits
```

SSH-GESTOR (`192.168.184.10`) es el bastión administrativo; no forma parte del flujo HTTP.

## Autenticación y sesiones

- El registro y el inicio de sesión usan los componentes de autenticación Livewire del starter kit.
- Laravel cifra y valida la cookie de sesión con `APP_KEY`. Page1 y Page2 deben usar exactamente la misma clave.
- `SESSION_DRIVER=database` guarda los datos en `sessions` en MariaDB; no depende del sistema de archivos local de ninguna Page.
- Ambas Pages deben usar el mismo `SESSION_COOKIE`, `SESSION_DOMAIN`, ruta de cookie, `APP_URL` y conexión de base de datos. Con un único hostname/IP de balanceador, `SESSION_DOMAIN=null` mantiene la cookie limitada al host usado por el navegador.
- El balanceador no necesita afinidad de sesión: ambas Pages consultan la misma tabla.

## Auditoría e IP de origen

El listener de `Illuminate\Auth\Events\Login` registra `user_id`, IP, etiqueta de servidor y agente de usuario. No registra contraseñas, cookies, tokens ni códigos de autenticación. La clave foránea conecta cada evento con su usuario y elimina los registros asociados si se elimina esa cuenta.

Laravel confía exclusivamente en `192.168.184.20` como proxy, en `bootstrap/app.php`. El Nginx del balanceador debe enviar `X-Forwarded-For` y `X-Forwarded-Proto`, y cada Page debe recibir la conexión desde esa IP privada. Así, Laravel puede resolver `Request::ip()` considerando la cadena de proxies sin aceptar esos encabezados de cualquier origen.

Si el balanceador se conecta a las Pages desde otra IP/interfaz, actualiza la lista de proxies confiables al origen exacto observado. No uses `*` ni confíes en todos los proxies: aceptar encabezados reenviados desde cualquier origen permitiría falsificar la IP auditada.

## Migraciones compartidas

La migración original del starter kit crea usuarios, recuperación de contraseñas y sesiones. Las migraciones nuevas se ejecutan una sola vez contra MariaDB central. No ejecutes migraciones simultáneas desde Page1 y Page2 durante el despliegue.
