-- Asignar permisos de proyectos a docente y estudiante
-- Los permisos 10-15 ya existen, solo falta el role_permissions

USE sistema_universitario;

-- Docente: asignar permisos de proyectos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'docente'
  AND p.code IN ('dashboard.view','user.access','password.recover','password.change.own','projects.create','projects.edit.own','projects.view.assigned','tasks.manage');

-- Estudiante: asignar permisos de proyectos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'estudiante'
  AND p.code IN ('dashboard.view','user.access','password.recover','password.change.own','projects.view.assigned','tasks.update.assigned');

-- Verificacion
SELECT r.code AS rol, GROUP_CONCAT(p.code ORDER BY p.id SEPARATOR ', ') AS permisos
FROM roles r
JOIN role_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
GROUP BY r.id, r.code
ORDER BY r.id;
