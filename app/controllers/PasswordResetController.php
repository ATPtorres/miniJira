<?php
/**
 * Sistema Universitario
 * app/controllers/PasswordResetController.php
 *
 * Flujo de recuperacion de contrasena:
 * 1) request(): solicitud con mensaje generico (anti enumeracion).
 * 2) reset():    restablecimiento con token de un solo uso y expiracion.
 *
 * Modo local: el enlace se registra en storage/logs/password_reset.log.
 */

declare(strict_types=1);

final class PasswordResetController
{
    /**
     * POST forgot-password.php
     * La respuesta es identica exista o no el correo (anti enumeracion).
     */
    public static function request(): void
    {
        if (!is_post()) {
            redirect('forgot-password.php');
        }
        requireCsrf();

        $email = normalize_email(post('email'));

        $errors = [];
        if (validate_required($email, 'correo electronico') !== '') {
            $errors['email'] = 'El correo electronico es obligatorio.';
        } elseif (validate_email_format($email) !== '') {
            $errors['email'] = 'El correo electronico no tiene un formato valido.';
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            flash_old_input(['email' => $email]);
            redirect('forgot-password.php');
        }

        $user = User::findByEmail($email);

        // Enlace de desarrollo: solo visible si app.env=local. Se limpia
        // en cada solicitud para no mostrar un enlace viejo.
        unset($_SESSION['_dev_reset_link']);

        // Solo generar token para cuentas activas; el mensaje no cambia.
        if ($user !== null && (int) $user['is_active'] === 1) {
            $ttl   = (int) config('auth.password_reset_ttl', 60);
            $token = bin2hex(random_bytes((int) config('auth.token_entropy_bytes', 32)));

            PasswordResetToken::createForUser(
                (int) $user['id'],
                PasswordResetToken::hashToken($token),
                $ttl
            );

            $link = base_url('reset-password.php?token=' . urlencode($token));

            // En modo local no hay SMTP: se registra el enlace en el log.
            MailService::sendPasswordReset((string) $user['email'], $link);

            $admins = User::byRole('admin');
            $userName = $user['first_name'] . ' ' . $user['last_name'];
            foreach ($admins as $admin) {
                Notification::create(
                    (int) $admin['id'],
                    'password_reset_request',
                    'Solicitud de recuperacion',
                    'El usuario "' . $userName . '" (' . $email . ') solicito recuperar su contrasena.',
                    null
                );
            }

            // MODO LOCAL: exponer el enlace en pantalla para pruebas sin SMTP.
            // devResetLink() devuelve null si env != local (produccion segura).
            $devLink = MailService::devResetLink($link);
            if ($devLink !== null) {
                $_SESSION['_dev_reset_link'] = $devLink;
            }
        }

        // Mensaje generico SIEMPRE, para no revelar si el correo existe.
        flash_set('success', 'Si el correo esta registrado, recibiras un enlace para restablecer tu contrasena.');
        redirect('forgot-password.php');
    }

    /**
     * POST reset-password.php
     * Valida token (no usado, no expirado), cambia la contrasena con
     * password_hash() y marca el token como usado.
     */
    public static function reset(): void
    {
        if (!is_post()) {
            redirect('forgot-password.php');
        }
        requireCsrf();

        $token    = (string) ($_POST['token'] ?? '');
        $password = post('password');
        $confirm  = post('password_confirmation');

        $errors = [];

        $pwError = validate_password_strength($password);
        if ($pwError !== '') {
            $errors['password'] = $pwError;
        }

        $confirmError = validate_password_confirmation($password, $confirm);
        if ($confirmError !== '') {
            $errors['password_confirmation'] = $confirmError;
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            redirect('reset-password.php?token=' . urlencode($token));
        }

        $record = PasswordResetToken::findValid(PasswordResetToken::hashToken($token));

        if ($record === null) {
            flash_set('error', 'El enlace de recuperacion no es valido, expiro o ya fue utilizado. Solicita uno nuevo.');
            redirect('forgot-password.php');
        }

        $newHash = password_hash($password, password_algo());

        User::updatePassword((int) $record['user_id'], $newHash);
        PasswordResetToken::markUsed((int) $record['id']);

        flash_set('success', 'Tu contrasena fue actualizada correctamente. Inicia sesion con tu nueva contrasena.');
        redirect('login.php');
    }
}
