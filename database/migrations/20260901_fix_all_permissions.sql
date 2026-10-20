-- Migracion correcta: crear permisos faltantes y asignarlos
-- Los permisos 10-15 del seed.sql nunca se insertaron (colision con roles.manage en id=10)

USE sistema_universitario;

-- 1. Insertar permisos de proyecto y tareas faltantes (sin ID explicito)
INSERT IGNORE INTO permissions (name, code, description) VALUES
('Crear proyectos', 'projects.create', 'Crear proyectos universitarios.'),
('Editar proyectos propios', 'projects.edit.own', 'Editar y eliminar logicamente sus proyectos.'),
('Ver proyectos asignados', 'projects.view.assigned', 'Consultar proyectos donde participa.'),
('Gestionar tareas', 'tasks.manage', 'Crear y editar tareas de sus proyectos.'),
('Actualizar tareas asignadas', 'tasks.update.assigned', 'Cambiar el estado de sus tareas asignadas.'),
('Gestionar todos los proyectos', 'projects.manage.all', 'Acceso administrativo a todos los proyectos.');

-- 2. Asignar permisos a docente
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'docente'
  AND p.code IN ('dashboard.view','user.access','password.recover','password.change.own',
                  'projects.create','projects.edit.own','projects.view.assigned','tasks.manage');

-- 3. Asignar permisos a estudiante
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'estudiante'
  AND p.code IN ('dashboard.view','user.access','password.recover','password.change.own',
                  'projects.view.assigned','tasks.update.assigned');

-- 4. Asignar todos los permisos a admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'admin';

-- Verificacion
SELECT r.code AS rol, GROUP_CONCAT(p.code ORDER BY p.id SEPARATOR ', ') AS permisos
FROM roles r
JOIN role_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
GROUP BY r.id, r.code
ORDER BY r.id;
