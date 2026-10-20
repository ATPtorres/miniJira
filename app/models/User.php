<?php
/**
 * Sistema Universitario
 * app/models/User.php
 *
 * Todas las consultas usan PDO con consultas preparadas (anti SQLi).
 * Nunca se devuelve ni manipula la contrasena en texto plano:
 * siempre se trabaja con password_hash() / password_verify().
 */

declare(strict_types=1);

final class User
{
    public static function findById(int $id): ?array
    {
        return self::findBy('u.id', $id);
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare(
            'SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email_lookup_hash = ? OR u.email = ?
             LIMIT 1'
        );
        $stmt->execute([EncryptionService::emailLookupHash($email), normalize_email($email)]);

        $user = $stmt->fetch();
        return $user === false ? null : self::hydrate($user);
    }

    private static function findBy(string $column, mixed $value): ?array
    {
        $sql = 'SELECT u.*, r.code AS role_code, r.name AS role_name
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE ' . $column . ' = ?
                LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute([$value]);

        $user = $stmt->fetch();
        return $user === false ? null : self::hydrate($user);
    }

    public static function existsByEmail(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /**
     * Crea un usuario. $data: first_name, last_name, email,
     * password_hash (ya generado con password_hash()), role_code.
     */
    public static function create(array $data): int
    {
        $roleId = Role::idByCode((string) ($data['role_code'] ?? 'estudiante'));
        if ($roleId === null) {
            throw new RuntimeException('Rol invalido: ' . (string) ($data['role_code'] ?? ''));
        }

        $email = normalize_email((string) $data['email']);
        $stmt = db()->prepare(
            'INSERT INTO users (role_id, first_name, last_name, email, email_encrypted, email_lookup_hash, password_hash, email_verified_at)
             VALUES (?, ?, ?, NULL, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $roleId,
            $data['first_name'],
            $data['last_name'],
            EncryptionService::encrypt($email),
            EncryptionService::emailLookupHash($email),
            $data['password_hash'],
        ]);

        return (int) db()->lastInsertId();
    }

    public static function updateLastLogin(int $id): void
    {
        $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $id]);
    }

    public static function updateRole(int $id, int $roleId): bool
    {
        $stmt = db()->prepare('UPDATE users SET role_id = ? WHERE id = ?');
        $stmt->execute([$roleId, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function updateProfile(int $id, array $data): void
    {
        $roleId = Role::idByCode((string) $data['role_code']);
        if ($roleId === null) {
            throw new RuntimeException('Rol invalido.');
        }
        $email = normalize_email((string) $data['email']);
        $stmt = db()->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, role_id = ?, email = NULL,
             email_encrypted = ?, email_lookup_hash = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['first_name'], $data['last_name'], $roleId,
            EncryptionService::encrypt($email), EncryptionService::emailLookupHash($email), $id,
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public static function all(): array
    {
        $users = db()
            ->query(
                'SELECT u.id, u.first_name, u.last_name, u.email, u.is_active,
                        u.last_login_at, u.created_at, u.email_encrypted,
                        r.name AS role_name, r.code AS role_code
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 ORDER BY u.id ASC'
            )
            ->fetchAll();

        return array_map(static fn (array $user): array => self::hydrate($user), $users);
    }

    public static function countByRole(): array
    {
        return db()
            ->query(
                'SELECT r.code, r.name, COUNT(u.id) AS total
                 FROM roles r
                 LEFT JOIN users u ON u.role_id = r.id
                 GROUP BY r.id, r.code, r.name
                 ORDER BY r.id ASC'
            )
            ->fetchAll();
    }

    /** Usuarios activos de un rol para asignaciones de proyectos. */
    public static function activeByRole(string $roleCode): array
    {
        $stmt = db()->prepare(
            'SELECT u.id, u.first_name, u.last_name, r.code AS role_code
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.code = ? AND u.is_active = 1
             ORDER BY u.first_name, u.last_name'
        );
        $stmt->execute([$roleCode]);
        return $stmt->fetchAll();
    }

    /** Todos los usuarios activos de un rol. */
    public static function byRole(string $roleCode): array
    {
        $stmt = db()->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, r.code AS role_code, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE r.code = ? AND u.is_active = 1
             ORDER BY u.first_name, u.last_name'
        );
        $stmt->execute([$roleCode]);
        $users = $stmt->fetchAll();
        return array_map(static fn (array $user): array => self::hydrate($user), $users);
    }

    public static function isActiveWithRole(int $userId, string $roleCode): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.is_active = 1 AND r.code = ?'
        );
        $stmt->execute([$userId, $roleCode]);
        return (int) $stmt->fetchColumn() === 1;
    }

    /** Convierte los campos privados almacenados cifrados para uso interno. */
    private static function hydrate(array $user): array
    {
        if (!empty($user['email_encrypted'])) {
            $user['email'] = EncryptionService::decrypt((string) $user['email_encrypted']);
        }

        unset($user['email_encrypted'], $user['email_lookup_hash']);
        return $user;
    }
}
