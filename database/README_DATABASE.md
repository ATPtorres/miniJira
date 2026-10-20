# Base de datos — sistema_universitario

## Motor y codificación
- MySQL/MariaDB (XAMPP), base: `sistema_universitario`
- Charset: `utf8mb4` · Collation: `utf8mb4_unicode_ci`

## Tablas (Sprint 1)

| Tabla | Propósito |
|---|---|
| `roles` | Roles del sistema: `admin`, `docente`, `estudiante` (`code` UNIQUE) |
| `permissions` | Catálogo de permisos (`code` UNIQUE) |
| `role_permissions` | Matriz permisos→roles (PK compuesta `role_id, permission_id`) |
| `users` | Usuarios; correo cifrado en `email_encrypted`, índice HMAC en `email_lookup_hash` y `password_hash` irreversible |
| `password_reset_tokens` | Tokens de recuperación; solo se guarda el **hash** del token (`token_hash`), con `expires_at` y `used_at` |

## Relaciones y borrado
- `users.role_id → roles.id` : `ON DELETE RESTRICT` (no se puede borrar un rol en uso)
- `role_permissions.role_id → roles.id` : `ON DELETE CASCADE`
- `role_permissions.permission_id → permissions.id` : `ON DELETE CASCADE`
- `password_reset_tokens.user_id → users.id` : `ON DELETE CASCADE`

## Orden de ejecución
1. `schema.sql` (crea BD + tablas)
2. `seed.sql` (roles, permisos, matriz)
3. Usuarios de desarrollo (los hashes NO vienen en SQL):

```
C:\xampp\php\php.exe database\create_user.php admin Administrador Admin admin@sistema.local "Clave.2026!"
C:\xampp\php\php.exe database\create_user.php docente Docente Demo docente@sistema.local "Clave.2026!"
C:\xampp\php\php.exe database\create_user.php estudiante Estudiante Demo estudiante@sistema.local "Clave.2026!"
```

## Verificación de datos sensibles
```sql
SELECT id, email, email_encrypted, email_lookup_hash, password_hash FROM users;
```
Los valores de `password_hash` deben empezar por `$argon2id$` o `$2y$`
(verificables con `password_verify()`), nunca ser el texto plano de la contraseña.
En la instalación migrada, `email` debe ser `NULL`; el correo recuperable se
almacena cifrado con AES-256-GCM y se consulta mediante su índice HMAC.

## Notas de seguridad
- Las contraseñas se generan con `password_hash($clave, PASSWORD_ARGON2ID|PASSWORD_DEFAULT)` vía script PHP; no se entregan hashes "inventados".
- La creación de usuarios y asignación de roles se controla con el permiso `users.manage`.
- Los roles y su matriz se gestionan en `public/admin/roles.php` con el permiso `roles.manage`.
