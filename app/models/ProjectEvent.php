<?php
/** Eventos personalizados del calendario de proyectos. */

declare(strict_types=1);

final class ProjectEvent
{
    public const TYPES = ['task' => 'Tarea', 'milestone' => 'Hito', 'delivery' => 'Entrega', 'custom' => 'Personalizado'];

    /** Tipos ofrecidos en los formularios (los eventos de tipo "task" son internos). */
    public const FORM_TYPES = ['custom' => 'Personalizado', 'milestone' => 'Hito', 'delivery' => 'Entrega'];

    public static function forProject(int $projectId, ?string $month = null): array
    {
        $sql = 'SELECT e.*, CONCAT(u.first_name, " ", u.last_name) AS creator_name
                FROM project_events e
                JOIN users u ON u.id = e.created_by_id
                WHERE e.project_id = ? AND e.deleted_at IS NULL';
        $params = [$projectId];
        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $sql .= ' AND e.event_date BETWEEN ? AND LAST_DAY(?)';
            $params[] = $month . '-01';
            $params[] = $month . '-01';
        }
        $sql .= ' ORDER BY e.event_date, e.event_time';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $eventId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM project_events WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$eventId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        return Project::managedMutation((int)$data['project_id'],static function()use($data):int{
        $stmt = db()->prepare(
            'INSERT INTO project_events (project_id, title, description, event_date, event_time, event_type, created_by_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['title'],
            $data['description'] ?: null,
            $data['event_date'],
            $data['event_time'] ?: null,
            $data['event_type'] ?? 'custom',
            $data['created_by_id'],
        ]);
        return (int) db()->lastInsertId();
        });
    }

    public static function update(int $eventId, array $data): void
    {
        $event=self::find($eventId);if(!$event)return;
        Project::managedMutation((int)$event['project_id'],static function()use($eventId,$data):void{
        $stmt = db()->prepare(
            'UPDATE project_events SET title = ?, description = ?, event_date = ?, event_time = ?, event_type = ?
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['event_date'],
            $data['event_time'] ?: null,
            $data['event_type'] ?? 'custom',
            $eventId,
        ]);
        });
    }

    public static function delete(int $eventId): void
    {
        $event=self::find($eventId);if(!$event)return;
        Project::managedMutation((int)$event['project_id'],static function()use($eventId):void{
        $stmt = db()->prepare('UPDATE project_events SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$eventId]);
        });
    }
}
