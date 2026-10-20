-- Agrega campo de prioridad a las tareas.
USE sistema_universitario;

ALTER TABLE project_tasks
  ADD COLUMN priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium'
  AFTER grade_comment;
