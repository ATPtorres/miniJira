<?php
/**
 * Cifrado autenticado para datos personales en reposo.
 *
 * Las contrasenas no se cifran: se almacenan con password_hash(), que es el
 * mecanismo correcto para secretos que no deben poder recuperarse. Este
 * servicio se usa para datos que la aplicacion si necesita volver a leer.
 */

declare(strict_types=1);

final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key('security.encryption_key'),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('No fue posible cifrar el dato sensible.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < 29) {
            throw new RuntimeException('El dato cifrado no tiene un formato valido.');
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key('security.encryption_key'),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('No fue posible descifrar el dato sensible.');
        }

        return $plaintext;
    }

    /** Indice no reversible para buscar un correo sin guardarlo en claro. */
    public static function emailLookupHash(string $email): string
    {
        return hash_hmac('sha256', normalize_email($email), self::key('security.email_lookup_key'));
    }

    private static function key(string $configKey): string
    {
        $configured = (string) config($configKey, '');
        if ($configured === '' || str_starts_with($configured, 'REEMPLAZA_')) {
            throw new RuntimeException('Configura una clave de cifrado segura antes de usar datos sensibles.');
        }

        return hash('sha256', $configured, true);
    }
}
