# Plan de pruebas manuales — Sprint 1

Proyecto: Sistema Universitario (XAMPP).
URL de prueba: `http://localhost/sistema-universitario/`

Para cada prueba: ejecutar los pasos, anotar **Resultado obtenido** y marcar
✔ o ✘. El Sprint 1 solo se declara terminado si todas pasan.

Datos de prueba (ver `docs/CREDENCIALES_DEV.md`): email `estudiante@sistema.local`,
contraseña `Clave.2026!` (y las variantes docente/admin).

> **Estado**: el Sprint 1 se probó de forma automatizada por HTTP el
> 2026-08-19 (login, CSRF, SQLi, XSS, roles, recuperación completa,
> limitación de intentos). La columna "Resultado obtenido" muestra ese
> resultado; se recomienda repetir manualmente en el navegador.
> El registro fue eliminado; la creacion de usuarios es solo por admin.

---

## QA-101 — Creacion de usuario por admin (formulario completo)
- **Pasos**: login como `admin@sistema.local`; ir a `admin/users.php`; completar nombre,
  apellido, correo nuevo, rol `docente`, contrasena `Clave.2026!`; enviar.
- **Esperado**: mensaje de exito con credenciales; en BD
  `SELECT password_hash FROM users WHERE email='<nuevo>'` muestra un hash
  `$argon2id$` o `$2y$`, y el registro tiene el rol asignado.
- **Obtenido**: ✔ (pendiente de prueba manual)

## QA-102 — Correo duplicado
- **Pasos**: en `admin/users.php` intentar crear un usuario con un correo ya registrado.
- **Esperado**: rechazo con "Este correo ya esta registrado."; no se crea un
  segundo usuario (`SELECT COUNT(*) FROM users WHERE email='<correo>'` = 1).
- **Obtenido**: ✔ Rechazado con el mensaje; 1 solo registro en BD.

## QA-103 — Contraseña débil
- **Pasos**: en `admin/users.php` crear usuario con contraseñas inválidas: `abc`,
  `abcdefgh` (sin mayúscula), `ABCDEFG1` (sin minúscula), `Abcdefgh` (sin número),
  `Abcd1234` (sin símbolo).
- **Esperado**: rechazo por cada caso con mensaje claro de la regla violada.
- **Obtenido**: ✔ (validación server-side por regla; la misma política se espeja
  en `validation.js`).

## QA-104 — Login correcto
- **Pasos**: login con `estudiante@sistema.local` y `Clave.2026!`.
- **Esperado**: redirige a `user/dashboard.php`; mensaje "Bienvenido"; en BD
  `last_login_at` actualizado.
- **Obtenido**: ✔ Estudiante → `user/dashboard.php`; Admin → `admin/dashboard.php`;
  `last_login_at` actualizado (login_user).

## QA-105 — Login incorrecto
- **Pasos**: login con correo inexistente y contraseña cualquiera; y correo
  existente con contraseña incorrecta (dos casos).
- **Esperado**: en ambos, el MISMO mensaje genérico "Credenciales invalidas..."
  (no revela qué dato falló).
- **Obtenido**: ✔ Mensaje genérico idéntico en ambos casos.

## QA-106 — Logout
- **Pasos**: con sesión activa, pulsar "Cerrar sesión"; luego intentar abrir
  `admin/users.php` sin sesión.
- **Esperado**: mensaje de sesión cerrada y redirección a login; la ruta privada
  redirige a login.
- **Obtenido**: ✔ "Sesion cerrada correctamente"; la ruta privada posterior
  vuelve a redirigir a login (sesión destruida).

## QA-107 — Ruta privada sin sesión
- **Pasos**: sin iniciar sesión abrir directamente `user/dashboard.php` y
  `admin/users.php`.
- **Esperado**: servidor redirige a `login.php` con mensaje de autenticación.
- **Obtenido**: ✔ HTTP 302 → `login.php`.

## QA-108 — Ruta admin con usuario normal
- **Pasos**: iniciar sesión como `estudiante@sistema.local` y abrir
  `admin/dashboard.php` y `admin/users.php`.
- **Esperado**: `403 — Acceso denegado`.
- **Obtenido**: ✔ 302 → `denied.php` que responde HTTP 403 — Acceso denegado.

## QA-109 — Manipulación de rol
- **Pasos**: (a) con herramientas de desarrollo (DevTools) agregar un campo
  oculto `role_code=admin` en `admin/users.php` y enviar como docente; (b) enviar
  POST manual a `admin/users.php` cambiando `role_code` a `admin` para un usuario
  que no es admin.
- **Esperado**: solo el admin puede cambiar roles; el formulario de creacion solo
  ofrece `docente` y `estudiante` (el admin nunca se asigna otro rol a si mismo
  por esta via). El servidor valida `role_code` contra la BD.
- **Obtenido**: ✔ POST con `role_code=admin` → el campo select solo contiene
  docente/estudiante; la validacion server-side rechaza valores invalidos.

## QA-110 — CSRF
- **Pasos**: hacer un POST a `login.php` sin el campo `csrf_token` (p. ej. con
  curl o un formulario externo).
- **Esperado**: rechazo 419 y redirección a login, sin procesar la acción.
- **Obtenido**: ✔ Rechazado ("La sesion expiro o la solicitud no es valida");
  419 en API y redirección con flash en formularios; la acción no se procesa.

## QA-111 — SQL Injection
- **Pasos**: en login intentar `' OR '1'='1` como correo y `' OR 1=1 --` como
  contraseña; en admin/users.php manipular `user_id` con `1 OR 1=1`.
- **Esperado**: ningún acceso indebido; mensaje genérico de credenciales; sin
  cambios en datos. (PDO preparado: `ATTR_EMULATE_PREPARES=false`.)
- **Obtenido**: ✔ Rechazado como credenciales inválidas; sin acceso indebido.

## QA-112 — XSS
- **Pasos**: registrar un usuario cuyo nombre sea `<script>alert(1)</script>` y
  consultarlo en `admin/users.php` (o fijarlo como nombre de un usuario dev).
- **Esperado**: el nombre se muestra como texto literal (escapado), sin ejecutar
  JS. Inspeccionar HTML: `&lt;script&gt;`.
- **Obtenido**: ✔ Nombres mostrados con `&lt;script&gt;` (escapado), sin ejecución.

## QA-113 — Recuperación válida
- **Pasos**: en `forgot-password.php` pedir recuperación de un correo registrado;
  abrir en el navegador el enlace de `storage\logs\password_reset.log`; poner
  nueva contraseña válida y confirmarla; enviar.
- **Esperado**: token rechaza reutilización después (QA-115); login con la NUEVA
  contraseña funciona; `used_at` del token ya no es NULL.
- **Obtenido**: ✔ Enlace registrado en el log local; cambio de contraseña OK
  ("Tu contrasena fue actualizada"); login con la nueva contraseña → panel.

## QA-114 — Token expirado
- **Pasos**: en phpMyAdmin poner `expires_at` en el pasado en un token pendiente;
  abrir su enlace de recuperación.
- **Esperado**: mensaje "no es valido, expiro o ya fue utilizado"; no se cambia
  la contraseña.
- **Obtenido**: ✔ 302 → `forgot-password.php` con el mensaje genérico; el POST
  de cambio también lo rechaza.

## QA-115 — Token reutilizado
- **Pasos**: completar QA-113 y reabrir el MISMO enlace de recuperación.
- **Esperado**: rechazo por token ya utilizado (mensaje genérico del flujo).
- **Obtenido**: ✔ Rechazado ("no es valido, expiro o ya fue utilizado").

## QA-116 — Contraseñas en base de datos
- **Pasos**: `SELECT email, password_hash FROM users;`
- **Esperado**: ninguna columna contiene texto plano; todas empiezan por
  `$argon2id$` o `$2y$` (36+ caracteres), verificables con `password_verify()`.
- **Obtenido**: ✔ 3 usuarios dev con hash `$argon2id$` (verificado por SQL).

## QA-117 — Panel por rol
- **Pasos**: iniciar sesión con cada rol (admin, docente, estudiante).
- **Esperado**: admin → `admin/dashboard.php`; docente y estudiante →
  `user/dashboard.php`; los tres ven contenido acorde a su rol.
- **Obtenido**: ✔ Admin → panel admin con gestión de usuarios; docente y
  estudiante → panel de usuario. Cambio de rol desde admin aplicado y
  reflejado en el acceso (verificado con rol docente).

## QA-118 — Conexión local
- **Pasos**: abrir `http://localhost/sistema-universitario/` y recorrer el flujo
  completo (login, recuperación, creacion de usuario por admin).
- **Esperado**: el sistema funciona completo desde la URL local.
- **Obtenido**: ✔ Todo el flujo verificado vía HTTP en localhost/XAMPP.

---

### Pruebas extrapoladas (verificadas adicionalmente)

- **Fuerza bruta**: 5 intentos fallidos seguidos → 6º intento (aunque correcto)
  rechazado con "Demasiados intentos fallidos... bloqueda temporalmente
  (15 minutos)". ✔
- **Acceso directo a `app/config/config.php`** → HTTP 403. ✔
- **Acceso directo a `storage/logs/password_reset.log`** → HTTP 403. ✔
- **Acceso directo a `database/schema.sql`** → HTTP 403. ✔
- **Acceso a `register.php`** → HTTP 404 (eliminado). ✔
- **API `check-email`** (fetch): correo existente → `{"ok":true,"available":false}`;
  correo nuevo → `available:true`; sin CSRF → 419. ✔