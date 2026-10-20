-- Agrega campos de calificacion a las tareas.
-- grade_result: nota final (0-100) asignada por el docente.
-- grade_comment: observacion opcional del docente.
USE sistema_universitario;

ALTER TABLE project_tasks
  ADD COLUMN grade_result SMALLINT UNSIGNED NULL AFTER points,
  ADD COLUMN grade_comment TEXT NULL AFTER grade_result;

ALTER TABLE project_tasks
  ADD CONSTRAINT chk_tasks_grade_result CHECK (grade_result IS NULL OR grade_result BETWEEN 0 AND 100);
