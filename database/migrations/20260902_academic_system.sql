-- =====================================================================
-- Sistema Universitario - Sprint Academico
-- database/migrations/20260902_academic_system.sql
-- Arquitectura profesional: carreras -> planes -> materias -> grupos
-- -> periodos -> asignaciones -> matriculas -> cursadas
-- IDEMPOTENTE. Ejecutar desde phpMyAdmin o mysql CLI.
-- =====================================================================

USE sistema_universitario;

-- ---------------------------------------------------------------------
-- 1. Periodos academicos (tiempo real: 2026-1, 2026-2)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(100) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_academic_periods_code (code),
  KEY idx_ap_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Carreras
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS careers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  duration_semesters TINYINT UNSIGNED NOT NULL DEFAULT 8,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_careers_code (code),
  KEY idx_careers_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Planes de estudio (versionado por carrera)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS study_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  career_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  version VARCHAR(20) NOT NULL DEFAULT '1.0',
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  valid_from DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_study_plans_career_code (career_id, code),
  KEY idx_sp_career (career_id),
  KEY idx_sp_current (is_current),
  CONSTRAINT fk_sp_career FOREIGN KEY (career_id) REFERENCES careers(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Materias (catalogo global reutilizable)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  credits TINYINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subjects_code (code),
  KEY idx_subjects_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Prerrequisitos entre materias (grafo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subject_prerequisites (
  subject_id INT UNSIGNED NOT NULL,
  required_subject_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (subject_id, required_subject_id),
  CONSTRAINT fk_spreq_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_spreq_required FOREIGN KEY (required_subject_id) REFERENCES subjects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_spreq_no_self CHECK (subject_id <> required_subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Plan -> Materia por semestre (malla curricular)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS study_plan_subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  study_plan_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  semester_number TINYINT UNSIGNED NOT NULL,
  is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sps_plan_subject (study_plan_id, subject_id),
  KEY idx_sps_plan_sem (study_plan_id, semester_number),
  KEY idx_sps_subject (subject_id),
  CONSTRAINT fk_sps_plan FOREIGN KEY (study_plan_id) REFERENCES study_plans(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sps_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_sps_semester CHECK (semester_number BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Grupos (seccion operativa por carrera+semestre+periodo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(150) NOT NULL,
  career_id INT UNSIGNED NOT NULL,
  study_plan_id INT UNSIGNED NULL,
  semester_number TINYINT UNSIGNED NOT NULL,
  academic_period_id INT UNSIGNED NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_groups_career_sem_period_code (career_id, semester_number, academic_period_id, code),
  KEY idx_groups_career (career_id),
  KEY idx_groups_period (academic_period_id),
  KEY idx_groups_plan (study_plan_id),
  CONSTRAINT fk_groups_career FOREIGN KEY (career_id) REFERENCES careers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_groups_plan FOREIGN KEY (study_plan_id) REFERENCES study_plans(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_groups_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_groups_sem CHECK (semester_number BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Asignaciones academicas (DOCENTE + MATERIA + GRUPO + PERIODO)
-- Entidad central. 1 fila = 1 docente imparte 1 materia a 1 grupo en 1 periodo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS teaching_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  group_id INT UNSIGNED NOT NULL,
  academic_period_id INT UNSIGNED NOT NULL,
  career_id INT UNSIGNED NOT NULL,
  status ENUM('activa','inactiva','finalizada') NOT NULL DEFAULT 'activa',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ta_teacher_subject_group_period (teacher_id, subject_id, group_id, academic_period_id),
  UNIQUE KEY uq_ta_subject_group_period (subject_id, group_id, academic_period_id),
  KEY idx_ta_teacher (teacher_id),
  KEY idx_ta_subject (subject_id),
  KEY idx_ta_group (group_id),
  KEY idx_ta_period (academic_period_id),
  KEY idx_ta_career (career_id),
  CONSTRAINT fk_ta_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ta_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ta_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ta_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ta_career FOREIGN KEY (career_id) REFERENCES careers(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Matriculas (ENROLLMENTS) - Estudiante en carrera/grupo/periodo/semestre
-- Historial: avanzar de semestre = nueva fila en nuevo periodo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  career_id INT UNSIGNED NOT NULL,
  study_plan_id INT UNSIGNED NULL,
  group_id INT UNSIGNED NOT NULL,
  academic_period_id INT UNSIGNED NOT NULL,
  semester_number TINYINT UNSIGNED NOT NULL,
  status ENUM('activo','inactivo','egresado','suspendido','retirado') NOT NULL DEFAULT 'activo',
  enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enroll_student_period (student_id, academic_period_id),
  KEY idx_enroll_student (student_id),
  KEY idx_enroll_career (career_id),
  KEY idx_enroll_group (group_id),
  KEY idx_enroll_period (academic_period_id),
  KEY idx_enroll_status (status),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enroll_career FOREIGN KEY (career_id) REFERENCES careers(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enroll_plan FOREIGN KEY (study_plan_id) REFERENCES study_plans(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_enroll_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_enroll_period FOREIGN KEY (academic_period_id) REFERENCES academic_periods(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_enroll_sem CHECK (semester_number BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Cursadas / Inscripciones a materias (EXPEDIENTE)
-- Estudiante cursa materia con docente en grupo/periodo. Aqui van notas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS course_enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id INT UNSIGNED NOT NULL,
  teaching_assignment_id INT UNSIGNED NOT NULL,
  status ENUM('inscrita','aprobada','reprobada','retirada','repitiendo','en_curso') NOT NULL DEFAULT 'inscrita',
  grade DECIMAL(4,2) NULL,
  enrolled_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ce_enroll_ta (enrollment_id, teaching_assignment_id),
  KEY idx_ce_enroll (enrollment_id),
  KEY idx_ce_ta (teaching_assignment_id),
  KEY idx_ce_status (status),
  CONSTRAINT fk_ce_enroll FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ce_ta FOREIGN KEY (teaching_assignment_id) REFERENCES teaching_assignments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_ce_grade CHECK (grade IS NULL OR (grade >= 0 AND grade <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Permisos academicos
-- ---------------------------------------------------------------------
INSERT IGNORE INTO permissions (name, code, description) VALUES
('Gestionar carreras', 'careers.manage', 'Crear y editar carreras.'),
('Gestionar planes de estudio', 'study_plans.manage', 'Crear planes y asignar materias por semestre.'),
('Gestionar materias', 'subjects.manage', 'Crear y editar catalogo de materias.'),
('Gestionar periodos academicos', 'periods.manage', 'Crear y gestionar periodos academicos.'),
('Gestionar grupos', 'groups.manage', 'Crear y gestionar grupos por carrera/periodo.'),
('Gestionar asignaciones academicas', 'assignments.manage', 'Asignar docentes a materias/grupos/periodos.'),
('Gestionar matriculas', 'enrollments.manage', 'Matricular estudiantes y gestionar inscripciones.'),
('Ver expediente academico', 'academic.view', 'Ver expediente y asignaciones.');

-- Admin: todos los permisos
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE code IN ('careers.manage','study_plans.manage','subjects.manage','periods.manage','groups.manage','assignments.manage','enrollments.manage','academic.view');

-- Docente: ver expediente de sus asignaciones
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE code IN ('academic.view');

-- Estudiante: ver su expediente
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE code IN ('academic.view');

-- ---------------------------------------------------------------------
-- 12. Datos iniciales de ejemplo
-- ---------------------------------------------------------------------
INSERT IGNORE INTO academic_periods (id, code, name, start_date, end_date, is_active) VALUES
(1, '2026-1', '2026 - Periodo 1 (Ene-Jun)', '2026-01-15', '2026-06-15', 1),
(2, '2026-2', '2026 - Periodo 2 (Ago-Dic)', '2026-08-15', '2026-12-15', 1);

INSERT IGNORE INTO careers (id, code, name, description, duration_semesters, is_active) VALUES
(1, 'IS', 'Ingenieria de Sistemas', 'Carrera de Ingenieria de Sistemas', 8, 1),
(2, 'LA', 'Licenciatura en Administracion', 'Carrera de Administracion de Empresas', 8, 1);

INSERT IGNORE INTO study_plans (id, career_id, code, name, version, is_current, valid_from, is_active) VALUES
(1, 1, 'IS-2024', 'Plan IS 2024', '2024', 1, '2024-01-01', 1),
(2, 2, 'LA-2024', 'Plan LA 2024', '2024', 1, '2024-01-01', 1);

INSERT IGNORE INTO subjects (id, code, name, description, credits, is_active) VALUES
(1, 'MAT101', 'Matematica I', 'Fundamentos de matematica', 4, 1),
(2, 'PROG101', 'Programacion I', 'Introduccion a la programacion', 5, 1),
(3, 'FIS101', 'Fisica I', 'Fundamentos de fisica', 4, 1),
(4, 'ADM101', 'Administracion I', 'Principios de administracion', 4, 1);

INSERT IGNORE INTO study_plan_subjects (study_plan_id, subject_id, semester_number, is_mandatory) VALUES
(1, 1, 1, 1),
(1, 2, 1, 1),
(1, 3, 2, 1),
(2, 4, 1, 1),
(2, 1, 1, 1);
