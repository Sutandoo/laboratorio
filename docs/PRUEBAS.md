# Pruebas automatizadas

## Base de pruebas

`phpunit.xml` define `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`. También usa sesiones y caché en memoria/arreglo. Por ello, `php artisan test` no usa la MariaDB del laboratorio, aunque el `.env` local apunte a ella.

Requisitos del PHP de pruebas: extensiones `pdo_sqlite` y `sqlite3`, además de las extensiones requeridas por Laravel.

## Cobertura solicitada

| Caso | Prueba |
|---|---|
| Registro | Las pruebas del starter kit crean una cuenta; se comprueba que la contraseña almacenada verifica con el hash de Laravel. |
| Inicio de sesión correcto | Se autentica con el componente Livewire original. |
| Auditoría de acceso correcto | Verifica que el evento de autenticación genera una fila con usuario, IP y etiqueta de servidor. |
| Inicio de sesión fallido | Verifica que una contraseña incorrecta no genera auditoría. |
| Privacidad del historial | El dashboard muestra los registros del usuario autenticado y excluye los de otra cuenta. |
| Etiqueta de servidor | Verifica que el dashboard usa el valor de configuración `laboratorio.server_label`. |

## Ejecutar

```bash
php artisan test
```

Para ejecutar un único archivo:

```bash
php artisan test tests/Feature/LoginAuditTest.php
```
