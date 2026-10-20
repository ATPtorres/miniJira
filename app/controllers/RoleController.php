<?php
/** Gestion de roles y matriz de permisos. */

declare(strict_types=1);

final class RoleController
{
    public static function create(): void
    {
        self::guard();
        $name = trim(post('name'));
        $code = strtolower(trim(post('code')));
        $description = trim(post('description'));
        $permissions = self::permissionsFromPost();

        $errors = [];
        if (validate_length($name, 'Nombre del rol', 2, 50) !== '') {
            $errors['name'] = validate_length($name, 'Nombre del rol', 2, 50);
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $code)) {
            $errors['code'] = 'El codigo debe tener entre 2 y 50 caracteres: letras minusculas, numeros o guion bajo.';
        } elseif (Role::byCode($code) !== null) {
            $errors['code'] = 'Ya existe un rol con ese codigo.';
        }
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'La descripcion no puede superar 255 caracteres.';
        }
        if ($permissions === null) {
            $errors['permissions'] = 'La seleccion de permisos no es valida.';
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            flash_old_input(['name' => $name, 'code' => $code, 'description' => $description]);
            redirect('admin/roles.php');
        }

        Role::create($name, $code, $description, $permissions);
        flash_set('success', 'Rol creado y permisos asignados correctamente.');
        redirect('admin/roles.php');
    }

    public static function updatePermissions(): void
    {
        self::guard();
        $roleId = (int) post('role_id');
        $role = Role::exists($roleId) ? self::roleById($roleId) : null;
        $permissions = self::permissionsFromPost();

        if ($role === null || $permissions === null) {
            flash_set('error', 'La solicitud no es valida.');
            redirect('admin/roles.php');
        }

        // Evita dejar sin administracion a la unica cuenta con control total.
        if ($role['code'] === 'admin') {
            $required = ['admin.access', 'users.manage', 'roles.manage'];
            if (array_diff($required, $permissions) !== []) {
                flash_set('error', 'El rol Administrador debe conservar los permisos administrativos esenciales.');
                redirect('admin/roles.php');
            }
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            Role::syncPermissions($roleId, $permissions, $pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        flash_set('success', 'Permisos del rol actualizados correctamente.');
        redirect('admin/roles.php');
    }

    private static function guard(): void
    {
        if (!is_post()) {
            redirect('admin/roles.php');
        }
        requireCsrf();
        requirePermission('roles.manage');
    }

    /** @return string[]|null */
    private static function permissionsFromPost(): ?array
    {
        $raw = $_POST['permissions'] ?? [];
        if (!is_array($raw)) {
            return null;
        }
        $codes = array_values(array_unique(array_filter($raw, 'is_string')));
        return count($codes) === count(Permission::validCodes($codes)) ? $codes : null;
    }

    private static function roleById(int $roleId): ?array
    {
        foreach (Role::all() as $role) {
            if ((int) $role['id'] === $roleId) {
                return $role;
            }
        }
        return null;
    }
}
