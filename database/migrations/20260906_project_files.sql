-- Archivos adjuntos de proyectos y tareas.
USE sistema_universitario;

CREATE TABLE IF NOT EXISTS project_files (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NULL,
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
  KEY idx_pf_uploader (uploaded_by_id),
  CONSTRAINT fk_pf_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pf_task FOREIGN KEY (task_id)
    REFERENCES project_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pf_uploader FOREIGN KEY (uploaded_by_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permisos para archivos
INSERT IGNORE INTO permissions (name, code, description) VALUES
('Subir archivos a proyectos', 'files.upload', 'Subir archivos adjuntos a proyectos y tareas.'),
('Eliminar archivos de proyectos', 'files.delete', 'Eliminar archivos adjuntos de sus proyectos.');

-- Docente: permisos de archivos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN ('files.upload', 'files.delete');

-- Admin: todos los permisos de archivos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE code IN ('files.upload', 'files.delete');
