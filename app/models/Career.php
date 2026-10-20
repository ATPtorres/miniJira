<?php
declare(strict_types=1);
final class Career
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM careers' . ($onlyActive ? ' WHERE is_active=1' : '') . ' ORDER BY name ASC';
        return db()->query($sql)->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM careers WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function findByCode(string $code): ?array
    {
        $stmt = db()->prepare('SELECT * FROM careers WHERE code=? LIMIT 1');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function create(array $d): int
    {
        $stmt = db()->prepare('INSERT INTO careers (code,name,description,duration_semesters,is_active) VALUES (?,?,?,?,?)');
        $stmt->execute([$d['code'],$d['name'],$d['description'] ?? null,(int)$d['duration_semesters'], $d['is_active'] ?? 1]);
        return (int)db()->lastInsertId();
    }
    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE careers SET code=?,name=?,description=?,duration_semesters=?,is_active=? WHERE id=?');
        $stmt->execute([$d['code'],$d['name'],$d['description'] ?? null,(int)$d['duration_semesters'], $d['is_active'] ?? 1, $id]);
    }
    public static function countSubjects(int $careerId): int
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM study_plans sp JOIN study_plan_subjects sps ON sps.study_plan_id=sp.id WHERE sp.career_id=?');
        $stmt->execute([$careerId]);
        return (int)$stmt->fetchColumn();
    }
}
