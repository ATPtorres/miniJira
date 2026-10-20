<?php
/** Modelo de permisos configurables por el administrador. */

declare(strict_types=1);

final class Permission
{
    public static function all(): array
    {
        return db()
            ->query('SELECT id, name, code, description FROM permissions ORDER BY name ASC')
            ->fetchAll();
    }

    /** @return string[] */
    public static function validCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes, 'is_string')));
        if ($codes === []) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($codes), '?'));
        $stmt = db()->prepare('SELECT code FROM permissions WHERE code IN (' . $marks . ')');
        $stmt->execute($codes);
        return array_map(static fn (array $row): string => (string) $row['code'], $stmt->fetchAll());
    }
}
