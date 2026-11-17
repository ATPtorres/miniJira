<?php
/**
 * Sistema Universitario
 * app/models/PasswordResetToken.php
 *
 * El token original (random_bytes) NUNCA se guarda en BD:
 * se almacena hash_hmac('sha256') y la validacion compara hashes.
 * Tokens de un solo uso, con expiracion; al generar uno nuevo se
 * invalidan los anteriores del mismo usuario (en una transaccion).
 */

declare(strict_types=1);

final class PasswordResetToken
{
    /**
     * Hash seguro del token (HMAC con la clave secreta de la app).
     */
    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.secret_key', ''));
    }

    /**
     * Crea un token para el usuario e invalida (marca usados) los
     * anteriores pendientes. Todo en una transaccion.
     *
     * @param int    $userId     id del usuario
     * @param string $tokenHash  hash del token original
     * @param int    $ttlMinutes duracion en minutos
     */
    public static function createForUser(int $userId, string $tokenHash, int $ttlMinutes = 60): void
    {
        $pdo = db();

        $pdo->beginTransaction();
        try {
            // Invalida tokens previos sin usar del mismo usuario.
            $pdo->prepare(
                'UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP
                 WHERE user_id = ? AND used_at IS NULL'
            )->execute([$userId]);

            // La expiracion se calcula con el reloj del servidor MySQL.
            $pdo->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (?, ?, (CURRENT_TIMESTAMP + (? * INTERVAL $$1 minute$$)))'
            )->execute([$userId, $tokenHash, $ttlMinutes]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Token valido: hash coincide, no usado y no expirado.
     */
    public static function findValid(string $tokenHash): ?array
    {
        $stmt = db()->prepare(
            'SELECT * FROM password_reset_tokens
             WHERE token_hash = ?
               AND used_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);

        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function markUsed(int $id): void
    {
        $stmt = db()->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$id]);
    }
}