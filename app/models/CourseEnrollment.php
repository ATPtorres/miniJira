<?php
declare(strict_types=1);
final class CourseEnrollment
{
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT ce.*, e.student_id, ta.subject_id, s.name AS subject_name, CONCAT(u.first_name," ",u.last_name) AS teacher_name FROM course_enrollments ce JOIN enrollments e ON e.id=ce.enrollment_id JOIN teaching_assignments ta ON ta.id=ce.teaching_assignment_id JOIN subjects s ON s.id=ta.subject_id JOIN users u ON u.id=ta.teacher_id WHERE ce.id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function enroll(int $enrollmentId, int $teachingAssignmentId): int
    {
        // Validar que assignment y enrollment comparten periodo/carrera/grupo coherente
        $en = Enrollment::find($enrollmentId);
        $ta = TeachingAssignment::find($teachingAssignmentId);
        if ($en === null || $ta === null) throw new RuntimeException('Matricula o asignacion no encontrada.');
        if ((int)$en['academic_period_id'] !== (int)$ta['academic_period_id']) throw new RuntimeException('Periodo no coincide entre matricula y asignacion.');
        if ((int)$en['career_id'] !== (int)$ta['career_id']) throw new RuntimeException('Carrera no coincide.');
        if ((int)$en['group_id'] !== (int)$ta['group_id']) throw new RuntimeException('El estudiante no pertenece al grupo de esa asignacion.');
        // Prerrequisitos: verificar que requiere aprobadas
        $prereqs = Subject::prerequisites((int)$ta['subject_id']);
        if ($prereqs !== []) {
            $approvedCodes = array_map(fn($r)=> (int)$r['subject_id'], db()->prepare('SELECT ta2.subject_id FROM course_enrollments ce2 JOIN teaching_assignments ta2 ON ta2.id=ce2.teaching_assignment_id JOIN enrollments e2 ON e2.id=ce2.enrollment_id WHERE e2.student_id=? AND ce2.status="aprobada"')->execute([(int)$en['student_id']]) ? [] : []);
            // re-query correctly
            $stmt = db()->prepare('SELECT ta2.subject_id FROM course_enrollments ce2 JOIN teaching_assignments ta2 ON ta2.id=ce2.teaching_assignment_id JOIN enrollments e2 ON e2.id=ce2.enrollment_id WHERE e2.student_id=? AND ce2.status="aprobada"');
            $stmt->execute([(int)$en['student_id']]);
            $approved = array_map(fn($r)=>(int)$r['subject_id'], $stmt->fetchAll());
            foreach ($prereqs as $p) {
                if (!in_array((int)$p['id'], $approved, true)) throw new RuntimeException('Prerrequisito no aprobado: '.$p['name']);
            }
        }
        $chk = db()->prepare('SELECT COUNT(*) FROM course_enrollments WHERE enrollment_id=? AND teaching_assignment_id=?');
        $chk->execute([$enrollmentId, $teachingAssignmentId]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('Ya inscrito en esa materia/grupo.');
        $stmt = db()->prepare('INSERT INTO course_enrollments (enrollment_id,teaching_assignment_id,status) VALUES (?,?,?)');
        $stmt->execute([$enrollmentId, $teachingAssignmentId, 'inscrita']);
        return (int)db()->lastInsertId();
    }
    public static function updateStatus(int $id, string $status, ?float $grade = null): void
    {
        $allowed = ['inscrita','aprobada','reprobada','retirada','repitiendo','en_curso'];
        if (!in_array($status, $allowed, true)) throw new RuntimeException('Estado no valido.');
        if ($grade !== null && ($grade < 0 || $grade > 100)) throw new RuntimeException('Calificacion 0-100.');
        $stmt = db()->prepare('UPDATE course_enrollments SET status=?, grade=? WHERE id=?');
        $stmt->execute([$status, $grade, $id]);
    }
    public static function byStudent(int $studentId, ?int $periodId = null): array
    {
        $sql = 'SELECT ce.*, s.name AS subject_name, s.code AS subject_code, s.credits, CONCAT(u.first_name," ",u.last_name) AS teacher_name, g.code AS group_code, ap.code AS period_code, e.semester_number FROM course_enrollments ce JOIN enrollments e ON e.id=ce.enrollment_id JOIN teaching_assignments ta ON ta.id=ce.teaching_assignment_id JOIN subjects s ON s.id=ta.subject_id JOIN users u ON u.id=ta.teacher_id JOIN `groups` g ON g.id=ta.group_id JOIN academic_periods ap ON ap.id=ta.academic_period_id WHERE e.student_id=?';
        $params = [$studentId];
        if ($periodId !== null) { $sql .= ' AND e.academic_period_id=?'; $params[] = $periodId; }
        $sql .= ' ORDER BY ap.start_date DESC, s.name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    public static function remove(int $id): void
    {
        db()->prepare('DELETE FROM course_enrollments WHERE id=?')->execute([$id]);
    }
}
