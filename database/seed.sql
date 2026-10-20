-- =====================================================================
-- Sistema Universitario
-- database/seed.sql
--
-- Datos iniciales: roles, permisos y matriz role_permissions.
-- Es idempotente (INSERT IGNORE): se puede ejecutar mas de una vez.
--
-- NOTA: Para una instalacion completa, usar install.sql en su lugar.
--       Este archivo es para recargar datos iniciales en una BD existente.
--
-- El usuario administrador NO se crea aqui con un hash fijo:
-- se crea con el script PHP seguro  database/create_user.php
-- (hace password_hash() real en tiempo de ejecucion).
-- =====================================================================

USE sistema_universitario;

-- ---------------------------------------------------------------------
-- Roles (IDs fijos: 1 admin, 2 docente, 3 estudiante)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO roles (id, name, code, description) VALUES
(1, 'Administrador', 'admin', 'Acceso total: gestiona usuarios, roles y rutas administrativas.'),
(2, 'Docente', 'docente', 'Gestiona proyectos universitarios, asigna estudiantes y define tareas.'),
(3, 'Estudiante', 'estudiante', 'Consulta proyectos asignados y gestiona sus tareas pendientes.');

-- ---------------------------------------------------------------------
-- Permisos (sin IDs explicitos para evitar colisiones)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO permissions (name, code, description) VALUES
('Ver panel propio', 'dashboard.view', 'Ver el panel correspondiente a su rol.'),
('Consultar usuarios', 'users.view', 'Listar y consultar los usuarios del sistema.'),
('Modificar roles', 'roles.assign', 'Cambiar el rol de un usuario.'),
('Acceder a rutas administrativas', 'admin.access', 'Entrar a /admin/*.'),
('Acceder a rutas de usuario', 'user.access', 'Entrar a /user/*.'),
('Solicitar recuperacion de contrasena', 'password.recover', 'Solicitar un enlace de restablecimiento de contrasena.'),
('Cambiar su propia contrasena', 'password.change.own', 'Cambiar su propia contrasena.'),
('Gestionar usuarios', 'users.manage', 'Crear usuarios y asignarles roles.'),
('Gestionar roles y permisos', 'roles.manage', 'Crear roles y configurar la matriz de permisos.'),
('Crear proyectos', 'projects.create', 'Crear proyectos universitarios.'),
('Editar proyectos propios', 'projects.edit.own', 'Editar y eliminar logicamente sus proyectos.'),
('Ver proyectos asignados', 'projects.view.assigned', 'Consultar proyectos donde participa.'),
('Gestionar tareas', 'tasks.manage', 'Crear y editar tareas de sus proyectos.'),
('Actualizar tareas asignadas', 'tasks.update.assigned', 'Cambiar el estado de sus tareas asignadas.'),
('Gestionar todos los proyectos', 'projects.manage.all', 'Acceso administrativo a todos los proyectos.');

-- ---------------------------------------------------------------------
-- Matriz role_permissions
--   Admin (15 permisos): todos
--   Docente (8 permisos): dashboard, user.access, password.*, projects.*
--   Estudiante (6 permisos): dashboard, user.access, password.*,
--                             projects.view.assigned, tasks.update.assigned
-- ---------------------------------------------------------------------
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE code IN ('dashboard.view','user.access','password.recover','password.change.own',
               'projects.create','projects.edit.own','projects.view.assigned','tasks.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE code IN ('dashboard.view','user.access','password.recover','password.change.own',
               'projects.view.assigned','tasks.update.assigned');
