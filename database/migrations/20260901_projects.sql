-- Proyectos, asignaciones y tareas Kanban para instalaciones existentes.
USE sistema_universitario;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL, description TEXT NOT NULL, objectives TEXT NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  responsible_teacher_id INT UNSIGNED NOT NULL, created_by_id INT UNSIGNED NOT NULL,
  deleted_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_projects_teacher (responsible_teacher_id), KEY idx_projects_deleted (deleted_at),
  CONSTRAINT fk_projects_teacher FOREIGN KEY (responsible_teacher_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projects_creator FOREIGN KEY (created_by_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_students (
  project_id INT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(project_id, student_id),
  CONSTRAINT fk_project_students_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_students_user FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, project_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL, description TEXT NULL, assigned_student_id INT UNSIGNED NULL,
  status ENUM('todo','doing','review','done') NOT NULL DEFAULT 'todo', due_date DATE NULL,
  deleted_at DATETIME NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tasks_project_status(project_id,status), KEY idx_tasks_assignee(assigned_student_id),
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_student FOREIGN KEY (assigned_student_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (id, name, code, description) VALUES
(10, 'Crear proyectos', 'projects.create', 'Crear proyectos universitarios.'),
(11, 'Editar proyectos propios', 'projects.edit.own', 'Editar y eliminar logicamente sus proyectos.'),
(12, 'Ver proyectos asignados', 'projects.view.assigned', 'Consultar proyectos donde participa.'),
(13, 'Gestionar tareas', 'tasks.manage', 'Crear y editar tareas de sus proyectos.'),
(14, 'Actualizar tareas asignadas', 'tasks.update.assigned', 'Cambiar el estado de sus tareas asignadas.'),
(15, 'Gestionar todos los proyectos', 'projects.manage.all', 'Acceso administrativo a todos los proyectos.');

INSERT IGNORE INTO role_permissions(role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE (r.code = 'admin')
   OR (r.code = 'docente' AND p.code IN ('projects.create','projects.edit.own','projects.view.assigned','tasks.manage'))
   OR (r.code = 'estudiante' AND p.code IN ('projects.view.assigned','tasks.update.assigned'));
