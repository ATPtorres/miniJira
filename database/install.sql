-- =====================================================================
-- Sistema Universitario
-- database/install.sql
--
-- Script de instalacion completa. Ejecutar una sola vez.
-- Compatible con MySQL 5.7+ / MariaDB 10.2+ / XAMPP.
--
-- Uso:
--   mysql -u root < install.sql
--   O desde phpMyAdmin: pestaña SQL -> pegar contenido -> Ejecutar.
--
-- Este script es IDEMPOTENTE: puede ejecutarse varias veces sin errores.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Base de datos
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS sistema_universitario
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sistema_universitario;

-- ---------------------------------------------------------------------
-- 2. Tablas
-- ---------------------------------------------------------------------

-- 2.1 Roles
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  code VARCHAR(50) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.2 Permisos
CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 Matriz de permisos por rol
CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id)
    REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id)
    REFERENCES permissions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.4 Usuarios
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  -- Email encriptado con AES-256-GCM; el hash permite buscarlo sin texto plano.
  email VARCHAR(190) NULL,
  email_encrypted TEXT NOT NULL,
  email_lookup_hash CHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_email_lookup_hash (email_lookup_hash),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id)
    REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.5 Tokens de recuperacion de contrasena
-- El token original NUNCA se guarda en texto plano: solo su hash.
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_prt_user (user_id),
  KEY idx_prt_token (token_hash),
  KEY idx_prt_expires (expires_at),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.6 Proyectos universitarios
CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT NOT NULL,
  objectives TEXT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  responsible_teacher_id INT UNSIGNED NOT NULL,
  created_by_id INT UNSIGNED NOT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_projects_teacher (responsible_teacher_id),
  KEY idx_projects_deleted (deleted_at),
  CONSTRAINT fk_projects_teacher FOREIGN KEY (responsible_teacher_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_projects_creator FOREIGN KEY (created_by_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.7 Asignacion de estudiantes a proyectos
CREATE TABLE IF NOT EXISTS project_students (
  project_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, student_id),
  CONSTRAINT fk_project_students_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_students_user FOREIGN KEY (student_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.8 Tareas del tablero Kanban: TO DO, DOING, IN REVIEW y DONE
CREATE TABLE IF NOT EXISTS project_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  assigned_student_id INT UNSIGNED NULL,
  points SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  grade_result SMALLINT UNSIGNED NULL,
  grade_comment TEXT NULL,
  priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  status ENUM('todo', 'doing', 'review', 'done') NOT NULL DEFAULT 'todo',
  due_date DATE NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tasks_project_status (project_id, status),
  KEY idx_tasks_assignee (assigned_student_id),
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_student FOREIGN KEY (assigned_student_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_tasks_points CHECK (points BETWEEN 1 AND 100),
  CONSTRAINT chk_tasks_grade_result CHECK (grade_result IS NULL OR grade_result BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.9 Eventos del calendario del proyecto
CREATE TABLE IF NOT EXISTS project_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  event_date DATE NOT NULL,
  event_time TIME NULL,
  event_type ENUM('task','milestone','delivery','custom') NOT NULL DEFAULT 'custom',
  created_by_id INT UNSIGNED NOT NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pe_project (project_id),
  KEY idx_pe_date (event_date),
  KEY idx_pe_type (event_type),
  CONSTRAINT fk_pe_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pe_creator FOREIGN KEY (created_by_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.10 Comentarios de proyectos (visibilidad general/privada, anonimato opcional)
-- y por tarea (task_id + parent_id: raiz del docente y respuestas).
CREATE TABLE IF NOT EXISTS project_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NULL,
  parent_id INT UNSIGNED NULL,
  author_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  visibility ENUM('project','private') NOT NULL DEFAULT 'project',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pc_project (project_id, deleted_at),
  KEY idx_pc_author (author_id),
  KEY idx_pc_task (task_id),
  KEY idx_pc_parent (parent_id),
  CONSTRAINT fk_pc_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pc_task FOREIGN KEY (task_id)
    REFERENCES project_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pc_parent FOREIGN KEY (parent_id)
    REFERENCES project_comments (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pc_author FOREIGN KEY (author_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.11 Archivos adjuntos de proyectos, tareas y comentarios
CREATE TABLE IF NOT EXISTS project_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NULL,
  comment_id INT UNSIGNED NULL,
  version_root_id INT UNSIGNED NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  deleted_by_id INT UNSIGNED NULL,
  uploaded_by_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  description VARCHAR(500) NULL,
  category ENUM('resource','template','instruction','format','other') NOT NULL DEFAULT 'resource',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pf_project (project_id),
  KEY idx_pf_task (task_id),
  KEY idx_pf_comment (comment_id),
  KEY idx_pf_uploader (uploaded_by_id),
  UNIQUE KEY uq_pf_version (version_root_id, version_number),
  CONSTRAINT fk_pf_version_root FOREIGN KEY (version_root_id)
    REFERENCES project_files (id) ON DELETE CASCADE,
  CONSTRAINT fk_pf_deleted_by FOREIGN KEY (deleted_by_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_pf_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pf_task FOREIGN KEY (task_id)
    REFERENCES project_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pf_comment FOREIGN KEY (comment_id)
    REFERENCES project_comments (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pf_uploader FOREIGN KEY (uploaded_by_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.12 Notificaciones
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id),
  KEY idx_notif_read (user_id, is_read),
  KEY idx_notif_type (type),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Roles del sistema
-- ---------------------------------------------------------------------
INSERT IGNORE INTO roles (id, name, code, description) VALUES
(1, 'Administrador', 'admin', 'Acceso total: gestiona usuarios, roles y rutas administrativas.'),
(2, 'Docente', 'docente', 'Gestiona proyectos universitarios, asigna estudiantes y define tareas.'),
(3, 'Estudiante', 'estudiante', 'Consulta proyectos asignados y gestiona sus tareas pendientes.');

-- ---------------------------------------------------------------------
-- 4. Permisos del sistema (sin IDs explicitos para evitar colisiones)
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
('Gestionar todos los proyectos', 'projects.manage.all', 'Acceso administrativo a todos los proyectos.'),
('Subir archivos a proyectos', 'files.upload', 'Subir archivos adjuntos a proyectos y tareas.'),
('Eliminar archivos de proyectos', 'files.delete', 'Eliminar archivos adjuntos de sus proyectos.');

-- ---------------------------------------------------------------------
-- 5. Matriz de permisos por rol
--
--   Admin (15 permisos): todos
--   Docente (8 permisos): dashboard, user.access, password.*, projects.create,
--                          projects.edit.own, projects.view.assigned, tasks.manage
--   Estudiante (6 permisos): dashboard, user.access, password.*,
--                             projects.view.assigned, tasks.update.assigned
-- ---------------------------------------------------------------------

-- Limpiar matriz existente para garantizar consistencia
TRUNCATE TABLE role_permissions;

-- Admin: todos los permisos
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Docente: permisos de gestion de proyectos
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE code IN (
  'dashboard.view',
  'user.access',
  'password.recover',
  'password.change.own',
  'projects.create',
  'projects.edit.own',
  'projects.view.assigned',
  'tasks.manage',
  'files.upload',
  'files.delete'
);

-- Admin: permisos de archivos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE code IN ('files.upload', 'files.delete');

-- Estudiante: permisos de consulta y actualizacion de tareas
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE code IN (
  'dashboard.view',
  'user.access',
  'password.recover',
  'password.change.own',
  'projects.view.assigned',
  'tasks.update.assigned'
);

-- ---------------------------------------------------------------------
-- 6. Verificacion final
-- ---------------------------------------------------------------------
SELECT r.code AS rol, COUNT(rp.permission_id) AS total_permisos,
       GROUP_CONCAT(p.code ORDER BY p.id SEPARATOR ', ') AS permisos
FROM roles r
JOIN role_permissions rp ON rp.role_id = r.id
JOIN permissions p ON p.id = rp.permission_id
GROUP BY r.id, r.code
ORDER BY r.id;


-- Migracion Beta: historial observado y entregas idempotentes.
-- No reconstruye ni inventa el progreso anterior a su instalacion.
CREATE TABLE IF NOT EXISTS project_progress_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  total_tasks INT UNSIGNED NOT NULL,
  done_tasks INT UNSIGNED NOT NULL,
  recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_progress_project_time (project_id, recorded_at, id),
  CONSTRAINT fk_progress_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_deliveries (
  dedupe_key VARCHAR(190) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_delivery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
