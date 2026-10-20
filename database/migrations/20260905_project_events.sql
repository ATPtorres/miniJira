-- Tabla de eventos personalizados del calendario del proyecto.
USE sistema_universitario;

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
