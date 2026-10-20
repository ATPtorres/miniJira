<?php
/**
 * Sistema Universitario
 * app/models/Role.php
 */

declare(strict_types=1);

final class Role
{
    public static function all(): array
    {
        return db()
            ->query('SELECT id, name, code, description FROM roles ORDER BY id ASC')
            ->fetchAll();
    }

    /** @return string[] */
    public static function permissionCodesForCode(string $code): array
    {
        static $cache = [];
        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $stmt = db()->prepare(
            'SELECT p.code
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN roles r ON r.id = rp.role_id
             WHERE r.code = ?
             ORDER BY p.code'
        );
        $stmt->execute([$code]);
        return $cache[$code] = array_map(static fn (array $row): string => (string) $row['code'], $stmt->fetchAll());
    }

    /** @return array<int, string[]> */
    public static function permissionsByRoleId(): array
    {
        $rows = db()->query(
            'SELECT rp.role_id, p.code
             FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             ORDER BY p.code'
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['role_id']][] = (string) $row['code'];
        }
        return $result;
    }

    /** Crea un rol y su matriz de permisos en una misma transaccion. */
    public static function create(string $name, string $code, string $description, array $permissionCodes): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO roles (name, code, description) VALUES (?, ?, ?)');
            $stmt->execute([$name, $code, $description !== '' ? $description : null]);
            $roleId = (int) $pdo->lastInsertId();
            self::syncPermissions($roleId, $permissionCodes, $pdo);
            $pdo->commit();
            return $roleId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Reemplaza de forma atomica los permisos asignados al rol. */
    public static function syncPermissions(int $roleId, array $permissionCodes, ?PDO $pdo = null): void
    {
        $pdo ??= db();
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);
        if ($permissionCodes === []) {
            return;
        }

        $marks = implode(',', array_fill(0, count($permissionCodes), '?'));
        $permissionIds = $pdo->prepare('SELECT id FROM permissions WHERE code IN (' . $marks . ')');
        $permissionIds->execute($permissionCodes);
        $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach ($permissionIds->fetchAll() as $permission) {
            $insert->execute([$roleId, (int) $permission['id']]);
        }
    }

    public static function byCode(string $code): ?array
    {
        $stmt = db()->prepare('SELECT id, name, code, description FROM roles WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);

        $role = $stmt->fetch();
        return $role === false ? null : $role;
    }

    public static function idByCode(string $code): ?int
    {
        $role = self::byCode($code);
        return $role === null ? null : (int) $role['id'];
    }

    public static function exists(int $id): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
