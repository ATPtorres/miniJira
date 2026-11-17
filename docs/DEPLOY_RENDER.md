# Despliegue de MiniJira en Render Free

MiniJira se ejecuta como Docker Web Service con PHP 8.3, Apache y PDO MySQL. La base de datos debe ser MySQL/MariaDB externa: Render no ofrece MySQL administrado gratuito. No se debe usar SQLite ni guardar datos persistentes en el disco efímero del servicio.

## 1. Base de datos externa

Crea una instancia MySQL compatible con conexiones remotas y TLS (elige un proveedor con nivel gratuito vigente). Crea una base vacía y guarda host, puerto, nombre, usuario, contraseña y, si el proveedor entrega un certificado CA, el archivo CA PEM.

Comprueba que el proveedor acepte conexiones desde Render y permita importar el SQL MySQL de `database/install.sql`. PostgreSQL no es sustituto directo: la aplicación usa PDO MySQL, collation utf8mb4 y sintaxis específica de MySQL.

## 2. Desplegar

1. En Render selecciona **New → Blueprint**.
2. Conecta este repositorio y selecciona la rama `BetaJiraFinal`.
3. Render detectará `render.yaml`. Confirma la creación del servicio gratuito `minijira`.
4. En Environment configura:
   - `SISTEMA_APP_URL`: URL pública completa, por ejemplo `https://minijira.onrender.com`.
   - `SISTEMA_DB_HOST`, `SISTEMA_DB_PORT`, `SISTEMA_DB_DATABASE`, `SISTEMA_DB_USERNAME`, `SISTEMA_DB_PASSWORD`.
   - `SISTEMA_DB_SSL_CA`: ruta del certificado CA PEM dentro del contenedor si tu proveedor exige CA. Si no exige CA, elimina la variable o déjala vacía.
5. Render genera las tres claves criptográficas automáticamente. No las regeneres después de crear usuarios/datos: son necesarias para descifrar correos y validar tokens. Conserva una copia segura fuera de GitHub.
6. Importa `database/install.sql` en la base externa. Aplica después las migraciones Beta en el orden indicado en `docs/BETA.md`. El proceso CLI `php database/migrate_beta.php` puede ejecutarse desde un entorno local seguro conectado a la misma base.

## 3. Primer usuario administrador

No existe registro público. Tras importar el esquema y aplicar migraciones, crea el primer administrador con `database/create_user.php` siguiendo el procedimiento de `README.md`, conectado a la base externa. No uses las credenciales de desarrollo en producción.

## 4. Límites del plan gratuito

- El servicio puede suspenderse por inactividad y tardar en responder al primer acceso.
- El filesystem del contenedor es efímero. Las entregas de archivos y logs locales pueden perderse al reiniciar o reemplazar el contenedor. Configura almacenamiento externo persistente antes de usarlo con archivos importantes.
- La base externa tiene sus propios límites, políticas de suspensión y copias de seguridad.
- Los recordatorios programados no se ejecutan continuamente dentro del Web Service gratuito. Configura un cron externo que invoque el comando de recordatorios cada 10 minutos.
- Configura SMTP en el proveedor de correo para restablecimiento de contraseña y notificaciones por email; el driver local de logs no envía correos.

## 5. Comprobación

Abre `https://TU-SERVICIO.onrender.com/health.php`: debe responder JSON con `status: ok`. Después prueba login, permisos, proyectos, tareas, archivos y reportes PDF. El health check solo confirma que Apache/PHP responde; no verifica la base de datos.
