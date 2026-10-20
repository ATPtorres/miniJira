<?php
declare(strict_types=1);
final class StudyPlan
{
    public static function all(): array
    {
        return db()->query('SELECT sp.*, c.name AS career_name, c.code AS career_code FROM study_plans sp JOIN careers c ON c.id=sp.career_id ORDER BY c.name, sp.version DESC')->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT sp.*, c.name AS career_name FROM study_plans sp JOIN careers c ON c.id=sp.career_id WHERE sp.id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function byCareer(int $careerId): array
    {
        $stmt = db()->prepare('SELECT * FROM study_plans WHERE career_id=? ORDER BY is_current DESC, version DESC');
        $stmt->execute([$careerId]);
        return $stmt->fetchAll();
    }
    public static function currentByCareer(int $careerId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM study_plans WHERE career_id=? AND is_current=1 LIMIT 1');
        $stmt->execute([$careerId]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function create(array $d): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (!empty($d['is_current'])) $pdo->prepare('UPDATE study_plans SET is_current=0 WHERE career_id=?')->execute([(int)$d['career_id']]);
            $stmt = $pdo->prepare('INSERT INTO study_plans (career_id,code,name,version,is_current,valid_from,is_active) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$d['career_id'],$d['code'],$d['name'],$d['version'] ?? '1.0', !empty($d['is_current']) ? 1 : 0, $d['valid_from'] ?: null, $d['is_active'] ?? 1]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
    public static function update(int $id, array $d): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if (!empty($d['is_current'])) {
                $careerId = (int)($d['career_id'] ?? self::find($id)['career_id']);
                $pdo->prepare('UPDATE study_plans SET is_current=0 WHERE career_id=? AND id<>?')->execute([$careerId, $id]);
            }
            $stmt = $pdo->prepare('UPDATE study_plans SET career_id=?,code=?,name=?,version=?,is_current=?,valid_from=?,is_active=? WHERE id=?');
            $stmt->execute([$d['career_id'],$d['code'],$d['name'],$d['version'] ?? '1.0', !empty($d['is_current']) ? 1 : 0, $d['valid_from'] ?: null, $d['is_active'] ?? 1, $id]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
    public static function subjects(int $planId): array
    {
        $stmt = db()->prepare('SELECT sps.*, s.code AS subject_code, s.name AS subject_name, s.credits FROM study_plan_subjects sps JOIN subjects s ON s.id=sps.subject_id WHERE sps.study_plan_id=? ORDER BY sps.semester_number, s.name');
        $stmt->execute([$planId]);
        return $stmt->fetchAll();
    }
    public static function subjectsBySemester(int $planId, int $semester): array
    {
        $stmt = db()->prepare('SELECT s.* FROM study_plan_subjects sps JOIN subjects s ON s.id=sps.subject_id WHERE sps.study_plan_id=? AND sps.semester_number=? ORDER BY s.name');
        $stmt->execute([$planId, $semester]);
        return $stmt->fetchAll();
    }
    public static function syncSubjects(int $planId, array $entries): void
    {
        // entries: [['subject_id'=>1,'semester'=>1], ...]
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM study_plan_subjects WHERE study_plan_id=?')->execute([$planId]);
            $stmt = $pdo->prepare('INSERT INTO study_plan_subjects (study_plan_id,subject_id,semester_number,is_mandatory) VALUES (?,?,?,?)');
            foreach ($entries as $e) {
                $stmt->execute([$planId, (int)$e['subject_id'], (int)$e['semester'], !empty($e['is_mandatory']) ? 1 : 1]);
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
