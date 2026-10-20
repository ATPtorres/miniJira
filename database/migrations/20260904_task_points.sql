-- Agrega campo de puntaje a las tareas (1-100).
USE sistema_universitario;

ALTER TABLE project_tasks
  ADD COLUMN points SMALLINT UNSIGNED NOT NULL DEFAULT 10
  AFTER assigned_student_id;

-- Restringir rango valido
ALTER TABLE project_tasks
  ADD CONSTRAINT chk_tasks_points CHECK (points BETWEEN 1 AND 100);
