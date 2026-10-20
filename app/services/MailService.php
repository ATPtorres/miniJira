<?php
/**
 * Sistema Universitario
 * app/services/MailService.php
 *
 * Envio de correo mediante SMTP o registro local para desarrollo.
 */

declare(strict_types=1);

final class MailService
{
    public static function sendPasswordReset(string $to, string $link): void
    {
        $driver = (string) config('mail.driver', 'log');

        if ($driver === 'log') {
            self::writeLog($to, $link, 'MODO LOCAL (driver=log): correo no enviado, enlace registrado en storage/logs/password_reset.log');
            return;
        }

        if ($driver === 'smtp') {
            self::sendSmtpPasswordReset($to, $link);
            return;
        }

        throw new RuntimeException('Driver de correo no soportado: ' . $driver);
    }

    /** Envia el enlace usando SMTP autenticado (AUTH LOGIN + TLS opcional). */
    private static function sendSmtpPasswordReset(string $to, string $link): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El destinatario de correo no es valido.');
        }

        $host = trim((string) config('mail.smtp_host', ''));
        $port = (int) config('mail.smtp_port', 587);
        $user = (string) config('mail.smtp_user', '');
        $password = (string) config('mail.smtp_password', '');
        $encryption = strtolower((string) config('mail.smtp_encryption', 'tls'));
        $from = trim((string) config('mail.from_address', ''));
        $fromName = trim((string) config('mail.from_name', 'Sistema Universitario'));

        if ($host === '' || $port <= 0 || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('La configuracion SMTP esta incompleta.');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('El tipo de cifrado SMTP no es valido.');
        }

        $endpoint = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($endpoint, $errno, $error, 20, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException('No fue posible conectar con el servidor SMTP: ' . $error);
        }

        try {
            stream_set_timeout($socket, 20);
            self::expect($socket, [220]);
            self::command($socket, 'EHLO localhost', [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No fue posible activar TLS para SMTP.');
                }
                self::command($socket, 'EHLO localhost', [250]);
            }

            if ($user !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($user), [334]);
                self::command($socket, base64_encode($password), [235]);
            }

            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $subject = 'Restablece tu contrasena';
            $body = '<p>Recibimos una solicitud para restablecer la contrasena de tu cuenta.</p>'
                . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Restablecer contrasena</a></p>'
                . '<p>El enlace vence en ' . (int) config('auth.password_reset_ttl', 60) . ' minutos y solo puede usarse una vez.</p>'
                . '<p>Si no solicitaste este cambio, puedes ignorar este correo.</p>';
            $headers = [
                'From: ' . self::encodedHeader($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodedHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . chunk_split(base64_encode($body), 76, "\r\n") . "\r\n.\r\n";
            fwrite($socket, $message);
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private static function command($socket, string $command, array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $expected);
    }

    private static function expect($socket, array $expected): void
    {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new RuntimeException('El servidor SMTP cerro la conexion inesperadamente.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('El servidor SMTP rechazo la solicitud (' . $code . ').');
        }
    }

    private static function encodedHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $value)) . '?=';
    }

    private static function writeLog(string $to, string $link, string $note): void
    {
        $dir = APP_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] SOLICITUD_RECUPERACION %s | para: %s | enlace: %s%s",
            date('Y-m-d H:i:s'),
            $note,
            $to,
            $link,
            PHP_EOL
        );

        @file_put_contents($dir . '/password_reset.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Depositario exclusivo del enlace en desarrollo: devuelve el enlace
     * SOLO si APP_ENV=local, para mostrarlo "en la vista de desarrollo".
     */
    public static function devResetLink(string $link): ?string
    {
        return (string) config('app.env', 'local') === 'local' ? $link : null;
    }
}
