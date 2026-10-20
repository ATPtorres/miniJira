<?php
/**
 * Sistema Universitario
 * app/controllers/AuthController.php
 *
 * Login y logout. La creacion de usuarios es exclusiva del
 * administrador desde el panel de gestion (admin/users.php).
 */

declare(strict_types=1);

final class AuthController
{
    /**
     * POST login.php
     * - CSRF obligatorio
     * - mensaje generico ante credenciales invalidas (sin revelar que dato fallo)
     * - limitacion basica de intentos por sesion
     * - regeneracion del ID de sesion y actualizacion de last_login_at
     */
    public static function login(): void
    {
        if (!is_post()) {
            redirect('login.php');
        }
        requireCsrf();

        $email    = normalize_email(post('email'));
        $password = post('password');

        if (is_login_locked()) {
            $mins = (int) ceil(lockout_seconds_left() / 60);
            flash_set('error', 'Demasiados intentos fallidos. La cuenta esta temporalmente bloqueada. Intenta de nuevo en ' . $mins . ' minuto(s).');
            redirect('login.php');
        }

        $errors = [];
        if (validate_required($email, 'correo electronico') !== '') {
            $errors['email'] = 'El correo electronico es obligatorio.';
        }
        if ($password === '') {
            $errors['password'] = 'La contrasena es obligatoria.';
        }

        $user = ($email !== '' && $password !== '') ? User::findByEmail($email) : null;

        $valid = $errors === [] && $user !== null
            && (int) $user['is_active'] === 1
            && password_verify($password, $user['password_hash']);

        if ($valid) {
            login_user($user);
            flash_set('success', 'Bienvenido, ' . $user['first_name'] . '.');
            redirect_to_panel();
        }

        // Mensaje generico: no se revela si el correo existe o si la clave fallo.
        register_failed_attempt();
        flash_set('error', is_login_locked()
            ? 'Demasiados intentos fallidos. Temporalmente bloqueado por seguridad.'
            : 'Credenciales invalidas. Verifica tu correo y tu contrasena.');
        redirect('login.php');
    }

    /**
     * POST user/change-password.php
     * Permite al usuario autenticado cambiar su propia contrasena de
     * forma manual: verifica la contrasena actual, valida la nueva
     * (politica de fortaleza + coincidencia) y la actualiza con
     * password_hash(). El permiso password.change.own lo autoriza.
     */
    public static function changeOwnPassword(): void
    {
        if (!is_post()) {
            redirect('user/change-password.php');
        }
        requireCsrf();
        requireAuth();
        requirePermission('password.change.own');

        $me = current_user();

        $current = post('current_password');
        $new     = post('password');
        $confirm = post('password_confirmation');

        $errors = [];

        if (validate_required($current, 'contrasena actual') !== '') {
            $errors['current_password'] = 'Debes introducir tu contrasena actual.';
        } elseif (!password_verify($current, (string) $me['password_hash'])) {
            $errors['current_password'] = 'La contrasena actual no es correcta.';
        }

        $pwError = validate_password_strength($new);
        if ($pwError !== '') {
            $errors['password'] = $pwError;
        }

        $confirmError = validate_password_confirmation($new, $confirm);
        if ($confirmError !== '') {
            $errors['password_confirmation'] = $confirmError;
        }

        // Buena practica: la nueva clave no debe coincidir con la actual.
        if ($new !== '' && $current !== '' && $new === $current) {
            $errors['password'] = 'La nueva contrasena debe ser distinta a la actual.';
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            redirect('user/change-password.php');
        }

        User::updatePassword((int) $me['id'], password_hash($new, password_algo()));

        flash_set('success', 'Tu contrasena fue actualizada correctamente. Usa la nueva contrasena en tu proximo inicio de sesion.');
        redirect_to_panel();
    }

    /**
     * POST logout.php (formulario con CSRF; GET se redirige).
     */
    public static function logout(): void
    {
        if (!is_post()) {
            redirect('login.php');
        }
        requireCsrf();

        $email = current_user()['email'] ?? '';

        logout_user(); // destruye la sesion y limpia sus variables

        // Nueva sesion limpia para poder mostrar el mensaje de exito.
        session_start();
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        flash_set('success', 'Sesion cerrada correctamente. Hasta pronto' . ($email !== '' ? ', ' . $email : '') . '.');
        redirect('login.php');
    }
}
