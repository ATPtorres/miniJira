<?php
declare(strict_types=1);
final class Enrollment
{
    public static function all(): array
    {
        return db()->query('SELECT e.*, CONCAT(u.first_name," ",u.last_name) AS student_name, c.name AS career_name, c.code AS career_code, g.code AS group_code, ap.code AS period_code FROM enrollments e JOIN users u ON u.id=e.student_id JOIN careers c ON c.id=e.career_id JOIN `groups` g ON g.id=e.group_id JOIN academic_periods ap ON ap.id=e.academic_period_id ORDER BY ap.start_date DESC, c.name, g.code')->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT e.*, CONCAT(u.first_name," ",u.last_name) AS student_name, c.name AS career_name, g.code AS group_code, ap.code AS period_code, ap.name AS period_name FROM enrollments e JOIN users u ON u.id=e.student_id JOIN careers c ON c.id=e.career_id JOIN `groups` g ON g.id=e.group_id JOIN academic_periods ap ON ap.id=e.academic_period_id WHERE e.id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function byStudent(int $studentId): array
    {
        $stmt = db()->prepare('SELECT e.*, c.name AS career_name, g.code AS group_code, ap.code AS period_code FROM enrollments e JOIN careers c ON c.id=e.career_id JOIN `groups` g ON g.id=e.group_id JOIN academic_periods ap ON ap.id=e.academic_period_id WHERE e.student_id=? ORDER BY ap.start_date DESC');
        $stmt->execute([$studentId]);
        return $stmt->fetchAll();
    }
    public static function activeByStudent(int $studentId, ?int $periodId = null): ?array
    {
        $sql = 'SELECT e.*, c.name AS career_name, g.code AS group_code, ap.code AS period_code FROM enrollments e JOIN careers c ON c.id=e.career_id JOIN `groups` g ON g.id=e.group_id JOIN academic_periods ap ON ap.id=e.academic_period_id WHERE e.student_id=? AND e.status="activo"';
        $params = [$studentId];
        if ($periodId !== null) { $sql .= ' AND e.academic_period_id=?'; $params[] = $periodId; }
        else $sql .= ' AND ap.is_active=1';
        $sql .= ' ORDER BY ap.start_date DESC LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function create(array $d): int
    {
        $chk = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE student_id=? AND academic_period_id=?');
        $chk->execute([(int)$d['student_id'], (int)$d['academic_period_id']]);
        if ((int)$chk->fetchColumn() > 0) throw new RuntimeException('El estudiante ya tiene matricula en ese periodo.');
        // Validar capacidad
        $cap = db()->prepare('SELECT capacity FROM `groups` WHERE id=?');
        $cap->execute([(int)$d['group_id']]);
        $capacity = (int)$cap->fetchColumn();
        $cnt = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE group_id=? AND academic_period_id=? AND status="activo"');
        $cnt->execute([(int)$d['group_id'], (int)$d['academic_period_id']]);
        if ((int)$cnt->fetchColumn() >= $capacity) throw new RuntimeException('El grupo esta lleno (capacidad '.$capacity.').');
        $stmt = db()->prepare('INSERT INTO enrollments (student_id,career_id,study_plan_id,group_id,academic_period_id,semester_number,status) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([(int)$d['student_id'],(int)$d['career_id'], !empty($d['study_plan_id']) ? (int)$d['study_plan_id'] : null, (int)$d['group_id'], (int)$d['academic_period_id'], (int)$d['semester_number'], $d['status'] ?? 'activo']);
        return (int)db()->lastInsertId();
    }
    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE enrollments SET career_id=?,study_plan_id=?,group_id=?,academic_period_id=?,semester_number=?,status=? WHERE id=?');
        $stmt->execute([(int)$d['career_id'], !empty($d['study_plan_id']) ? (int)$d['study_plan_id'] : null, (int)$d['group_id'], (int)$d['academic_period_id'], (int)$d['semester_number'], $d['status'] ?? 'activo', $id]);
    }
    public static function courseEnrollments(int $enrollmentId): array
    {
        $stmt = db()->prepare('SELECT ce.*, s.name AS subject_name, s.code AS subject_code, CONCAT(u.first_name," ",u.last_name) AS teacher_name, g.code AS group_code, ap.code AS period_code FROM course_enrollments ce JOIN teaching_assignments ta ON ta.id=ce.teaching_assignment_id JOIN subjects s ON s.id=ta.subject_id JOIN users u ON u.id=ta.teacher_id JOIN `groups` g ON g.id=ta.group_id JOIN academic_periods ap ON ap.id=ta.academic_period_id WHERE ce.enrollment_id=? ORDER BY s.name');
        $stmt->execute([$enrollmentId]);
        return $stmt->fetchAll();
    }
}
