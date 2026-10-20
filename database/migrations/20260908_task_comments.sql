-- Comentarios por tarea: hilo de 2 niveles (raiz del docente + respuestas).
-- Reutiliza project_comments (task_id ya existia); se agrega parent_id.
USE sistema_universitario;

ALTER TABLE project_comments
  ADD COLUMN parent_id INT UNSIGNED NULL AFTER task_id,
  ADD KEY idx_pc_task (task_id),
  ADD KEY idx_pc_parent (parent_id),
  ADD CONSTRAINT fk_pc_parent FOREIGN KEY (parent_id)
    REFERENCES project_comments (id) ON DELETE CASCADE ON UPDATE CASCADE;
