<?php
declare(strict_types=1);
final class TeachingAssignment
{
    public static function all(): array
    {
        return db()->query('SELECT ta.*, CONCAT(u.first_name," ",u.last_name) AS teacher_name, s.name AS subject_name, s.code AS subject_code, g.code AS group_code, g.name AS group_name, ap.code AS period_code, c.name AS career_name FROM teaching_assignments ta JOIN users u ON u.id=ta.teacher_id JOIN subjects s ON s.id=ta.subject_id JOIN `groups` g ON g.id=ta.group_id JOIN academic_periods ap ON ap.id=ta.academic_period_id JOIN careers c ON c.id=ta.career_id ORDER BY ap.start_date DESC, c.name, s.name')->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT ta.*, CONCAT(u.first_name," ",u.last_name) AS teacher_name, s.name AS subject_name, g.code AS group_code, ap.code AS period_code, c.name AS career_name FROM teaching_assignments ta JOIN users u ON u.id=ta.teacher_id JOIN subjects s ON s.id=ta.subject_id JOIN `groups` g ON g.id=ta.group_id JOIN academic_periods ap ON ap.id=ta.academic_period_id JOIN careers c ON c.id=ta.career_id WHERE ta.id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function byTeacher(int $teacherId, ?int $periodId = null): array
    {
        $sql = 'SELECT ta.*, s.name AS subject_name, s.code AS subject_code, g.code AS group_code, ap.code AS period_code, c.name AS career_name FROM teaching_assignments ta JOIN subjects s ON s.id=ta.subject_id JOIN `groups` g ON g.id=ta.group_id JOIN academic_periods ap ON ap.id=ta.academic_period_id JOIN careers c ON c.id=ta.career_id WHERE ta.teacher_id=?';
        $params = [$teacherId];
        if ($periodId !== null) { $sql .= ' AND ta.academic_period_id=?'; $params[] = $periodId; }
        $sql .= ' ORDER BY ap.start_date DESC, s.name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    public static function students(int $assignmentId): array
    {
        $stmt = db()->prepare('SELECT u.id, u.first_name, u.last_name, ce.status AS course_status, ce.grade, e.semester_number, g.code AS group_code FROM course_enrollments ce JOIN enrollments e ON e.id=ce.enrollment_id JOIN users u ON u.id=e.student_id JOIN `groups` g ON g.id=e.group_id WHERE ce.teaching_assignment_id=? ORDER BY u.last_name, u.first_name');
        $stmt->execute([$assignmentId]);
        return $stmt->fetchAll();
    }
    public static function create(array $d): int
    {
        // Validar unicidad: 1 materia+grupo+periodo = 1 docente
        $chk = db()->prepare('SELECT COUNT(*) FROM teaching_assignments WHERE subject_id=? AND group_id=? AND academic_period_id=?');
        $chk->execute([(int)$d['subject_id'], (int)$d['group_id'], (int)$d['academic_period_id']]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('Ya existe una asignacion para esa materia/grupo/periodo.');
        $stmt = db()->prepare('INSERT INTO teaching_assignments (teacher_id,subject_id,group_id,academic_period_id,career_id,status) VALUES (?,?,?,?,?,?)');
        $stmt->execute([(int)$d['teacher_id'],(int)$d['subject_id'],(int)$d['group_id'],(int)$d['academic_period_id'],(int)$d['career_id'], $d['status'] ?? 'activa']);
        return (int)db()->lastInsertId();
    }
    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE teaching_assignments SET teacher_id=?,subject_id=?,group_id=?,academic_period_id=?,career_id=?,status=? WHERE id=?');
        $stmt->execute([(int)$d['teacher_id'],(int)$d['subject_id'],(int)$d['group_id'],(int)$d['academic_period_id'],(int)$d['career_id'], $d['status'] ?? 'activa', $id]);
    }
    public static function delete(int $id): void
    {
        // No borrar si tiene cursadas
        $chk = db()->prepare('SELECT COUNT(*) FROM course_enrollments WHERE teaching_assignment_id=?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('No se puede eliminar: tiene estudiantes inscritos historicamente. Cambie estado a inactiva.');
        db()->prepare('DELETE FROM teaching_assignments WHERE id=?')->execute([$id]);
    }
}
