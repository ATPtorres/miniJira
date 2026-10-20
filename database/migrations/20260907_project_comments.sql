-- Comentarios de proyectos: visibilidad general/privada, anonimato opcional
-- y adjuntos multimedia reutilizando project_files.
USE sistema_universitario;

CREATE TABLE IF NOT EXISTS project_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NULL,
  author_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  visibility ENUM('project','private') NOT NULL DEFAULT 'project',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pc_project (project_id, deleted_at),
  KEY idx_pc_author (author_id),
  CONSTRAINT fk_pc_project FOREIGN KEY (project_id)
    REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pc_task FOREIGN KEY (task_id)
    REFERENCES project_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_pc_author FOREIGN KEY (author_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adjuntos de comentarios: columna opcional en project_files.
ALTER TABLE project_files
  ADD COLUMN comment_id INT UNSIGNED NULL AFTER task_id,
  ADD KEY idx_pf_comment (comment_id),
  ADD CONSTRAINT fk_pf_comment FOREIGN KEY (comment_id)
    REFERENCES project_comments (id) ON DELETE CASCADE ON UPDATE CASCADE;
