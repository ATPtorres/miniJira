<?php
declare(strict_types=1);
final class AcademicPeriod
{
    public static function all(): array
    {
        return db()->query('SELECT * FROM academic_periods ORDER BY start_date DESC')->fetchAll();
    }
    public static function active(): array
    {
        $stmt = db()->prepare('SELECT * FROM academic_periods WHERE is_active=1 ORDER BY start_date DESC');
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM academic_periods WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function findByCode(string $code): ?array
    {
        $stmt = db()->prepare('SELECT * FROM academic_periods WHERE code=? LIMIT 1');
        $stmt->execute([$code]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
    public static function create(array $d): int
    {
        $stmt = db()->prepare('INSERT INTO academic_periods (code,name,start_date,end_date,is_active) VALUES (?,?,?,?,?)');
        $stmt->execute([$d['code'],$d['name'],$d['start_date'],$d['end_date'], $d['is_active'] ?? 1]);
        return (int)db()->lastInsertId();
    }
    public static function update(int $id, array $d): void
    {
        $stmt = db()->prepare('UPDATE academic_periods SET code=?,name=?,start_date=?,end_date=?,is_active=? WHERE id=?');
        $stmt->execute([$d['code'],$d['name'],$d['start_date'],$d['end_date'], $d['is_active'] ?? 1, $id]);
    }
    public static function current(): ?array
    {
        $stmt = db()->prepare('SELECT * FROM academic_periods WHERE is_active=1 ORDER BY start_date DESC LIMIT 1');
        $stmt->execute();
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }
}
