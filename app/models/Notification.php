<?php
/** Modelo de notificaciones in-app para todos los roles. */

declare(strict_types=1);

final class Notification
{
    public const TYPES = [
        'task_assigned',
        'task_status_changed',
        'task_deadline_reminder',
        'task_graded',
        'project_created',
        'project_student_added',
        'project_student_removed',
        'event_created',
        'file_uploaded',
        'comment_added',
        'teaching_assignment',
        'enrollment_created',
        'password_reset_request',
    ];

    /** Inserta una notificacion y retorna su ID. */
    public static function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        $stmt = db()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $title, $message, $link]);
        return (int) db()->lastInsertId();
    }

    /** Retorna las ultimas N notificaciones de un usuario. */
    public static function forUser(int $userId, int $limit = 20): array
    {
        $stmt = db()->prepare(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /** Conteo de notificaciones no leidas. */
    public static function unreadCount(int $userId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /** Marca una notificacion como leida (solo si pertenece al usuario). */
    public static function markRead(int $notifId, int $userId): void
    {
        $stmt = db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notifId, $userId]);
    }

    /** Marca todas las notificaciones de un usuario como leidas. */
    public static function markAllRead(int $userId): void
    {
        $stmt = db()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$userId]);
    }

    /** Elimina una notificacion (solo si pertenece al usuario). */
    public static function delete(int $notifId, int $userId): void
    {
        $stmt = db()->prepare(
            'DELETE FROM notifications WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$notifId, $userId]);
    }

    /** Retorna todas las notificaciones de un usuario con paginacion. */
    public static function paginated(int $userId, int $page = 1, int $perPage = 15, ?string $typeFilter = null, ?string $readFilter = null): array
    {
        $where = 'WHERE user_id = ?';
        $params = [$userId];

        if ($typeFilter !== null && $typeFilter !== '') {
            $where .= ' AND type = ?';
            $params[] = $typeFilter;
        }
        if ($readFilter === 'read') {
            $where .= ' AND is_read = 1';
        } elseif ($readFilter === 'unread') {
            $where .= ' AND is_read = 0';
        }

        $countStmt = db()->prepare('SELECT COUNT(*) FROM notifications ' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = db()->prepare(
            'SELECT * FROM notifications ' . $where . ' ORDER BY created_at DESC LIMIT ? OFFSET ?'
        );
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => (int) ceil($total / $perPage),
        ];
    }

    /** Retorna labels legibles para los tipos de notificacion. */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'task_assigned'           => 'Tarea asignada',
            'task_status_changed'     => 'Cambio de estado',
            'task_deadline_reminder'  => 'Recordatorio',
            'task_graded'             => 'Calificacion',
            'project_created'         => 'Proyecto',
            'project_student_added'   => 'Asignacion a proyecto',
            'project_student_removed' => 'Remocion de proyecto',
            'event_created'           => 'Evento',
            'file_uploaded'           => 'Archivo',
            'comment_added'           => 'Comentario',
            'teaching_assignment'     => 'Asignacion docente',
            'enrollment_created'      => 'Inscripcion',
            'password_reset_request'  => 'Recuperacion',
            default                   => $type,
        };
    }
}
