<?php
/**
 * Sistema Universitario
 * app/controllers/UserController.php
 *
 * Gestion de usuarios desde el panel de administracion:
 * crear usuarios, consultar y modificar roles.
 */

declare(strict_types=1);

final class UserController
{
    /**
     * POST admin/users.php — Crear un nuevo usuario.
     * Solo admin; CSRF obligatorio.
     *
     * Si el campo contrasena esta vacio, se genera una segura automaticamente.
     * Las credenciales se muestran en pantalla para que el admin las
     * distribuya fisicamente.
     */
    public static function createUser(): void
    {
        if (!is_post()) {
            redirect('admin/users.php');
        }
        requireCsrf();
        requirePermission('users.manage');

        $firstName = post('first_name');
        $lastName  = post('last_name');
        $email     = normalize_email(post('email'));
        $roleCode  = strtolower(post('role_code'));
        $password  = post('password');

        $errors = [];

        if (validate_required($firstName, 'nombre') !== '') {
            $errors['first_name'] = 'El nombre es obligatorio.';
        } elseif (validate_length($firstName, 'Nombre', 2, 80) !== '') {
            $errors['first_name'] = validate_length($firstName, 'Nombre', 2, 80);
        }

        if (validate_required($lastName, 'apellido') !== '') {
            $errors['last_name'] = 'El apellido es obligatorio.';
        } elseif (validate_length($lastName, 'Apellido', 2, 80) !== '') {
            $errors['last_name'] = validate_length($lastName, 'Apellido', 2, 80);
        }

        if (validate_required($email, 'correo electronico') !== '') {
            $errors['email'] = 'El correo electronico es obligatorio.';
        } elseif (validate_email_format($email) !== '') {
            $errors['email'] = 'El correo electronico no tiene un formato valido.';
        } elseif (User::existsByEmail($email)) {
            $errors['email'] = 'Este correo ya esta registrado.';
        }

        if (Role::byCode($roleCode) === null) {
            $errors['role_code'] = 'El rol seleccionado no es valido.';
        }

        // La contrasena es opcional: si esta vacia se genera una segura.
        if ($password !== '') {
            $pwError = validate_password_strength($password);
            if ($pwError !== '') {
                $errors['password'] = $pwError;
            }
        } else {
            $password = generate_secure_password(12);
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            flash_old_input([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'role_code'  => $roleCode,
            ]);
            redirect('admin/users.php');
        }

        $hash = password_hash($password, password_algo());

        User::create([
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'email'         => $email,
            'password_hash' => $hash,
            'role_code'     => $roleCode,
        ]);

        // Las credenciales se muestran una sola vez para distribuirlas fisicamente.
        flash_set('success',
            'Usuario creado correctamente. Credenciales para entrega fisica: '
            . '<br><strong>Correo:</strong> ' . e($email)
            . '<br><strong>Contrasena:</strong> ' . e($password)
            . '<br><em>Anota estas credenciales y entregalas de forma segura al usuario.</em>'
        );
        redirect('admin/users.php');
    }

    /**
     * POST admin/users.php — Cambiar el rol de un usuario.
     * Solo admin; rol validado contra la BD; CSRF obligatorio.
     */
    public static function changeRole(): void
    {
        if (!is_post()) {
            redirect('admin/users.php');
        }
        requireCsrf();
        requirePermission('users.manage');

        $userId   = (int) post('user_id');
        $roleCode = strtolower(post('role_code'));

        $roleId = Role::idByCode($roleCode);

        if ($userId <= 0 || $roleId === null) {
            flash_set('error', 'La solicitud no es valida.');
            redirect('admin/users.php');
        }

        if ($userId === current_user_id() && $roleCode !== 'admin') {
            flash_set('error', 'No puedes quitarte el rol de administrador a ti mismo.');
            redirect('admin/users.php');
        }

        User::updateRole($userId, $roleId);
        flash_set('success', 'Rol del usuario actualizado correctamente.');
        redirect('admin/users.php');
    }

    public static function updateUser(): void
    {
        if (!is_post()) redirect('admin/users.php');
        requireCsrf();
        requirePermission('users.manage');
        $userId = (int) post('user_id');
        $existing = $userId > 0 ? User::findById($userId) : null;
        if ($existing === null) {
            flash_set('error', 'El usuario solicitado no existe.');
            redirect('admin/users.php');
        }
        $firstName = post('first_name'); $lastName = post('last_name');
        $email = normalize_email(post('email')); $roleCode = strtolower(post('role_code'));
        $errors = [];
        foreach ([['first_name', $firstName, 'Nombre'], ['last_name', $lastName, 'Apellido']] as [$key, $value, $label]) {
            $error = validate_length($value, $label, 2, 80);
            if ($error !== '') $errors[$key] = $error;
        }
        if (validate_email_format($email) !== '') $errors['email'] = 'El correo electronico no tiene un formato valido.';
        $sameEmailUser = $email !== '' ? User::findByEmail($email) : null;
        if ($sameEmailUser !== null && (int) $sameEmailUser['id'] !== $userId) $errors['email'] = 'Este correo ya esta registrado.';
        if (Role::byCode($roleCode) === null) $errors['role_code'] = 'El rol seleccionado no es valido.';
        if ($userId === current_user_id() && $roleCode !== 'admin') $errors['role_code'] = 'No puedes quitarte el rol de administrador a ti mismo.';
        if ($errors !== []) {
            flash_form_errors($errors);
            redirect('admin/users.php?edit=' . $userId);
        }
        User::updateProfile($userId, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'role_code' => $roleCode,
        ]);
        flash_set('success', 'Datos del usuario actualizados correctamente.');
        redirect('admin/users.php');
    }

    public static function toggleActive(): void
    {
        if (!is_post()) redirect('admin/users.php');
        requireCsrf();
        requirePermission('users.manage');
        $userId = (int) post('user_id');
        $user = $userId > 0 ? User::findById($userId) : null;
        if ($user === null || $userId === current_user_id()) {
            flash_set('error', 'No puedes dar de baja tu propia cuenta.');
            redirect('admin/users.php');
        }
        $active = (int) $user['is_active'] !== 1;
        User::setActive($userId, $active);
        flash_set('success', $active ? 'Usuario reactivado correctamente.' : 'Usuario dado de baja logicamente.');
        redirect('admin/users.php');
    }
}
