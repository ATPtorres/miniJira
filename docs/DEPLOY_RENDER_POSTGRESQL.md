# Despliegue PostgreSQL en Render Free

Esta rama separa la variante PostgreSQL de `BetaJiraFinal`. No modifica la rama MySQL original.

## Incluye

- PHP 8.3 + Apache + `pdo_pgsql` en Docker.
- Conexión PostgreSQL por variables `SISTEMA_DB_*` o `DATABASE_URL`.
- Esquema PostgreSQL para proyectos, Kanban, archivos, comentarios, notificaciones, clases y catálogo/matrícula académica.
- Migraciones Beta adaptadas a PostgreSQL.
- Blueprint de Render con Web Service y PostgreSQL Free.
- Adaptaciones para las expresiones MySQL que aún utiliza parte del código heredado.

## Desplegar

1. Abre [Render Blueprint](https://render.com/deploy?repo=https://github.com/ATPtorres/miniJira/tree/PostgreSQL-Migration).
2. Revisa el Blueprint y confirma la creación del servicio web y de la base PostgreSQL.
3. Espera a que termine el build y el deploy.
4. En el servicio web, configura `SISTEMA_APP_URL` con la URL HTTPS pública asignada por Render.
5. Instala el esquema en la base vacía desde un equipo local con PHP y `pdo_pgsql`:
   ```bash
   SISTEMA_DB_HOST=... SISTEMA_DB_PORT=5432 SISTEMA_DB_DATABASE=... \
   SISTEMA_DB_USERNAME=... SISTEMA_DB_PASSWORD=... SISTEMA_DB_SSLMODE=require \
   php database/postgresql/migrate.php
   ```
   Los valores están en la sección Connect de PostgreSQL en Render. También puedes ejecutar `database/postgresql/install.sql` con psql o pgAdmin.
6. Crea el usuario administrador con el procedimiento CLI existente, ejecutándolo localmente con las mismas variables PostgreSQL.
7. Prueba login, roles, clases, proyectos, Kanban, tareas, comentarios, archivos, calendario, notificaciones, PDF y catálogo académico.

## Migrar datos desde MySQL

Haz primero un respaldo de MySQL. Instala el esquema PostgreSQL en una base vacía y ejecuta localmente:

```bash
SISTEMA_MYSQL_HOST=127.0.0.1 \
SISTEMA_MYSQL_DATABASE=sistema_universitario \
SISTEMA_MYSQL_USERNAME=root \
SISTEMA_MYSQL_PASSWORD=... \
SISTEMA_DB_HOST=... SISTEMA_DB_PORT=5432 \
SISTEMA_DB_DATABASE=... SISTEMA_DB_USERNAME=... \
SISTEMA_DB_PASSWORD=... SISTEMA_DB_SSLMODE=require \
php database/postgresql/import_mysql.php
```

El importador copia las tablas existentes, respeta IDs, omite duplicados y reajusta secuencias. Requiere que el PHP local tenga habilitadas `pdo_mysql` y `pdo_pgsql`. Conserva exactamente las claves `SISTEMA_ENCRYPTION_KEY` y `SISTEMA_EMAIL_LOOKUP_KEY` usadas por la instalación MySQL: sin ellas los correos cifrados no podrán descifrarse ni buscarse. Compara conteos y verifica relaciones antes de cambiar usuarios a la nueva instancia.

## Variables

| Variable | Uso |
|---|---|
| `SISTEMA_APP_URL` | URL HTTPS pública |
| `SISTEMA_APP_SECRET` | Clave HMAC generada por Render |
| `SISTEMA_ENCRYPTION_KEY` | Clave AES (conserva la anterior si importas usuarios) |
| `SISTEMA_EMAIL_LOOKUP_KEY` | Clave de búsqueda HMAC (conserva la anterior si importas usuarios) |
| `SISTEMA_DB_HOST` | Host PostgreSQL |
| `SISTEMA_DB_PORT` | Puerto PostgreSQL (5432) |
| `SISTEMA_DB_DATABASE` | Nombre de base |
| `SISTEMA_DB_USERNAME` | Usuario |
| `SISTEMA_DB_PASSWORD` | Contraseña |
| `SISTEMA_DB_SSLMODE` | `require` en Render |
| `SISTEMA_TIMEZONE` | `America/La_Paz` |

## Límites del plan gratuito

- Render Free PostgreSQL tiene una duración limitada (actualmente 30 días); no es una base gratuita permanente.
- Los Web Services Free se suspenden tras inactividad y el almacenamiento local del contenedor es efímero.
- Los archivos en `storage/uploads` no son persistentes tras reinicios, suspensión o redeploy. Para uso real se necesita almacenamiento de objetos externo o un servicio con disco persistente.
- El servicio gratuito puede tardar en responder al primer acceso después de dormir.

## Estado de validación

Los archivos de configuración, esquema y compatibilidad están preparados en esta rama, pero aquí no es posible ejecutar un build Docker ni conectarse a una instancia PostgreSQL real. Completa el despliegue de prueba y valida todas las operaciones antes de considerarlo listo para producción. La rama MySQL `BetaJiraFinal` permanece intacta.
