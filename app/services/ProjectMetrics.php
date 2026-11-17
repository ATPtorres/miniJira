<?php
declare(strict_types=1);

/** Unica definicion de avance para tarjetas, analitica, API y PDF. */
final class ProjectMetrics
{
    public const LABELS = ['todo' => 'Pendiente', 'doing' => 'En proceso', 'review' => 'En revision', 'done' => 'Terminada'];

    public static function percentage(int $done, int $total): int
    {
        return $total > 0 ? (int) round(max(0, min($done, $total)) / $total * 100) : 0;
    }

    public static function fromCounts(array $counts): array
    {
        $result = [];
        foreach (self::LABELS as $status => $_) {
            $result[$status] = max(0, (int) ($counts[$status] ?? $counts[$status . '_count'] ?? 0));
        }
        $result['total'] = array_sum($result);
        $result['progress'] = self::percentage($result['done'], $result['total']);
        $result['completed'] = $result['total'] > 0 && $result['done'] === $result['total'];
        return $result;
    }

    public static function forProject(int $id): array
    {
        $stmt = db()->prepare('SELECT status, COUNT(*) AS amount FROM project_tasks WHERE project_id=? AND deleted_at IS NULL GROUP BY status');
        $stmt->execute([$id]);
        return self::fromCounts(array_column($stmt->fetchAll(), 'amount', 'status'));
    }

    public static function timeline(array $project, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable();
        $start = new DateTimeImmutable($project['start_date'] . ' 00:00:00');
        $end = new DateTimeImmutable($project['end_date'] . ' 23:59:59');
        $duration = max(1, $end->getTimestamp() - $start->getTimestamp());
        $elapsed = max(0, min($duration, $now->getTimestamp() - $start->getTimestamp()));
        return [
            'progress' => (int) floor($elapsed / $duration * 100),
            'expired' => $now > $end,
            'days_remaining' => max(0, (int) $now->setTime(0, 0)->diff($end->setTime(0, 0))->format('%r%a')),
            'days_overdue' => max(0, (int) $end->setTime(0, 0)->diff($now->setTime(0, 0))->format('%r%a')),
        ];
    }

    public static function students(int $projectId): array
    {
        $stmt = db()->prepare('SELECT u.id, CONCAT(u.first_name, " ", u.last_name) AS name,
            COUNT(t.id) AS total, COUNT(t.id) FILTER (WHERE t.status=$$done$$) AS done,
            COALESCE(SUM(t.points),0) AS points, COALESCE(SUM(t.grade_result),0) AS earned
            FROM project_students ps JOIN users u ON u.id=ps.student_id
            JOIN projects p ON p.id=ps.project_id JOIN classroom_members cm ON cm.classroom_id=p.classroom_id AND cm.student_id=u.id AND cm.is_active=1
            LEFT JOIN project_tasks t ON t.project_id=ps.project_id AND t.assigned_student_id=u.id AND t.deleted_at IS NULL
            WHERE ps.project_id=? AND u.is_active=1 AND EXISTS(SELECT 1 FROM roles r WHERE r.id=u.role_id AND r.code="estudiante") GROUP BY u.id ORDER BY u.first_name, u.last_name');
        $stmt->execute([$projectId]);
        $manager=Project::userCanManage($projectId,current_user_id(),can('projects.manage.all'));
        return array_map(static function (array $s) use ($manager): array {
            if (!$manager && (int)$s['id']!==current_user_id()) $s['earned']=null;
            $s['progress'] = self::percentage((int) $s['done'], (int) $s['total']);
            return $s;
        }, $stmt->fetchAll());
    }

    /** Called under the project row lock, in the same transaction as the task change. */
    public static function record(int $projectId): void
    {
        $metrics = self::forProject($projectId);
        $last = db()->prepare('SELECT total_tasks, done_tasks FROM project_progress_history WHERE project_id=? ORDER BY id DESC LIMIT 1');
        $last->execute([$projectId]);
        $row = $last->fetch();
        if ($row && (int) $row['total_tasks'] === $metrics['total'] && (int) $row['done_tasks'] === $metrics['done']) return;
        $stmt = db()->prepare('INSERT INTO project_progress_history(project_id,total_tasks,done_tasks) VALUES(?,?,?)');
        $stmt->execute([$projectId, $metrics['total'], $metrics['done']]);
    }

    public static function history(int $projectId, string $from, string $to): array
    {
        $stmt = db()->prepare('SELECT h.* FROM project_progress_history h
            JOIN (SELECT MAX(id) AS id FROM project_progress_history
              WHERE project_id=? AND recorded_at>=? AND recorded_at<? GROUP BY DATE(recorded_at)) last ON last.id=h.id
            ORDER BY h.recorded_at, h.id');
        $stmt->execute([$projectId, $from . ' 00:00:00', (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00']);
        return array_map(static function (array $h): array {
            return ['date' => substr($h['recorded_at'], 0, 10), 'progress' => self::percentage((int) $h['done_tasks'], (int) $h['total_tasks'])];
        }, $stmt->fetchAll());
    }

    public static function dateRange(?string $from, ?string $to): array
    {
        $from = $from ?: date('Y-m-d', strtotime('-30 days'));
        $to = $to ?: date('Y-m-d');
        foreach ([$from, $to] as $date) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Fecha no valida');
        }
        if ($from > $to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) {
            throw new InvalidArgumentException('Selecciona un periodo de hasta 366 dias');
        }
        return [$from, $to];
    }
}
