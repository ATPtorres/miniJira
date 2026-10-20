<?php
declare(strict_types=1);
final class AcademicGroup
{
    public static function all(): array
    {
        return db()->query('SELECT g.*, c.name AS career_name, c.code AS career_code, ap.code AS period_code, ap.name AS period_name FROM `groups` g JOIN careers c ON c.id=g.career_id JOIN academic_periods ap ON ap.id=g.academic_period_id ORDER BY ap.start_date DESC, c.name, g.code')->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT g.*, c.name AS career_name, ap.code AS period_code FROM `groups` g JOIN careers c ON c.id=g.career_id JOIN academic_periods ap ON ap.id=g.academic_period_id WHERE g.id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function byPeriod(int $periodId): array
    {
        $stmt = db()->prepare('SELECT g.*, c.name AS career_name FROM `groups` g JOIN careers c ON c.id=g.career_id WHERE g.academic_period_id=? ORDER BY c.name, g.semester_number, g.code');
        $stmt->execute([$periodId]);
        return $stmt->fetchAll();
    }
    public static function byCareerSemester(int $careerId, int $semester, int $periodId): array
    {
        $stmt = db()->prepare('SELECT * FROM `groups` WHERE career_id=? AND semester_number=? AND academic_period_id=? AND is_active=1 ORDER BY code');
        $stmt->execute([$careerId, $semester, $periodId]);
        return $stmt->fetchAll();
    }
    public static function create(array $d): int
    {
        $stmt = db()->prepare('INSERT INTO `groups` (code,name,career_id,study_plan_id,semester_number,academic_period_id,capacity,is_active) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$d['code'],$d['name'],(int)$d['career_id'], !empty($d['study_plan_id']) ? (int)$d['study_plan_id'] : null, (int)$d['semester_number'], (int)$d['academic_period_id'], (int)($d['capacity'] ?? 30), $d['is_active'] ?? 1]);
        return (int)db()->lastInsertId();
    }
    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE `groups` SET code=?,name=?,career_id=?,study_plan_id=?,semester_number=?,academic_period_id=?,capacity=?,is_active=? WHERE id=?');
        $stmt->execute([$d['code'],$d['name'],(int)$d['career_id'], !empty($d['study_plan_id']) ? (int)$d['study_plan_id'] : null, (int)$d['semester_number'], (int)$d['academic_period_id'], (int)($d['capacity'] ?? 30), $d['is_active'] ?? 1, $id]);
    }
    public static function countEnrollments(int $groupId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE group_id=? AND status="activo"');
        $stmt->execute([$groupId]);
        return (int)$stmt->fetchColumn();
    }
}
