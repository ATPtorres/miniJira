-- Migracion para instalaciones existentes. Ejecutar una vez en phpMyAdmin.
USE sistema_universitario;

ALTER TABLE users MODIFY email VARCHAR(190) NULL;
ALTER TABLE users ADD COLUMN email_encrypted TEXT NULL AFTER email;
ALTER TABLE users ADD COLUMN email_lookup_hash CHAR(64) NULL AFTER email_encrypted;
ALTER TABLE users ADD UNIQUE KEY uq_users_email_lookup_hash (email_lookup_hash);

INSERT IGNORE INTO permissions (name, code, description) VALUES
('Gestionar usuarios', 'users.manage', 'Crear usuarios y asignarles roles.'),
('Gestionar roles y permisos', 'roles.manage', 'Crear roles y configurar la matriz de permisos.');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'admin' AND p.code IN ('users.manage', 'roles.manage');
