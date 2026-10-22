-- Contexto mínimo para tareas; la universidad conserva la matrícula oficial.
CREATE TABLE IF NOT EXISTS classrooms (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 external_code VARCHAR(100) NOT NULL,
 subject VARCHAR(150) NOT NULL,
 career VARCHAR(150) NOT NULL,
 semester_number TINYINT UNSIGNED NOT NULL,
 group_name VARCHAR(80) NOT NULL,
 period VARCHAR(80) NOT NULL,
 teacher_id INT UNSIGNED NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_class_external (external_code),
 KEY idx_class_teacher_active (teacher_id,is_active,period),
 KEY idx_class_context (period,career,semester_number),
 CONSTRAINT fk_class_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS classroom_members (
 classroom_id INT UNSIGNED NOT NULL,
 student_id INT UNSIGNED NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(classroom_id,student_id),
 KEY idx_class_member_access(student_id,is_active,classroom_id),
 CONSTRAINT fk_cm_class FOREIGN KEY(classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
 CONSTRAINT fk_cm_student FOREIGN KEY(student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS classroom_audit (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 classroom_id INT UNSIGNED NOT NULL,
 actor_id INT UNSIGNED NULL,
 action VARCHAR(30) NOT NULL,
 details MEDIUMTEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_class_audit(classroom_id,id),
 CONSTRAINT fk_ca_class FOREIGN KEY(classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
 CONSTRAINT fk_ca_actor FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
