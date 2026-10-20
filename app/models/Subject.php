<?php
declare(strict_types=1);
final class Subject
{
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM subjects' . ($onlyActive ? ' WHERE is_active=1' : '') . ' ORDER BY name ASC';
        return db()->query($sql)->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM subjects WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function findByCode(string $code): ?array
    {
        $stmt = db()->prepare('SELECT * FROM subjects WHERE code=? LIMIT 1');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function create(array $d): int
    {
        $stmt = db()->prepare('INSERT INTO subjects (code,name,description,credits,is_active) VALUES (?,?,?,?,?)');
        $stmt->execute([$d['code'],$d['name'],$d['description'] ?? null, $d['credits'] !== '' ? (int)$d['credits'] : null, $d['is_active'] ?? 1]);
        return (int)db()->lastInsertId();
    }
    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE subjects SET code=?,name=?,description=?,credits=?,is_active=? WHERE id=?');
        $stmt->execute([$d['code'],$d['name'],$d['description'] ?? null, $d['credits'] !== '' ? (int)$d['credits'] : null, $d['is_active'] ?? 1, $id]);
    }
    public static function prerequisites(int $subjectId): array
    {
        $stmt = db()->prepare('SELECT s.* FROM subject_prerequisites sp JOIN subjects s ON s.id=sp.required_subject_id WHERE sp.subject_id=? ORDER BY s.name');
        $stmt->execute([$subjectId]);
        return $stmt->fetchAll();
    }
    public static function setPrerequisites(int $subjectId, array $requiredIds): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM subject_prerequisites WHERE subject_id=?')->execute([$subjectId]);
            $stmt = $pdo->prepare('INSERT INTO subject_prerequisites (subject_id, required_subject_id) VALUES (?,?)');
            foreach (array_unique(array_map('intval', $requiredIds)) as $rid) {
                if ($rid > 0 && $rid !== $subjectId) $stmt->execute([$subjectId, $rid]);
            }
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
