<?php
/** Tareas para el tablero visual Kanban de cada proyecto. */

declare(strict_types=1);

final class Task
{
    public const STATUSES = ['todo', 'doing', 'review', 'done'];
    public const PRIORITIES = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'urgent' => 'Urgente'];

    // Transiciones permitidas para ESTUDIANTES: todas menos hacia "done".
    // "done" es exclusivo del docente (calificar y completar); el resto
    // puede avanzar, retroceder o saltar columnas libremente.
    public const STUDENT_ALLOWED = [
        'todo'   => ['doing', 'review'],
        'doing'  => ['todo', 'review'],
        'review' => ['todo', 'doing'],
        'done'   => ['todo', 'doing', 'review'],
    ];

    /** Verifica si una transicion es valida para estudiante. */
    public static function canStudentTransition(string $from, string $to): bool
    {
        return in_array($to, self::STUDENT_ALLOWED[$from] ?? [], true);
    }

    /**
     * Verifica si una tarea esta bloqueada:
     * - Si esta calificada (done + grade_result NOT NULL)
     * - Si vencio la fecha limite y no esta completada
     */
    public static function isLocked(int $taskId): bool
    {
        $task = self::find($taskId);
        if ($task === null) {
            return true;
        }
        // Bloqueada si ya calificada
        if ($task['status'] === 'done' && $task['grade_result'] !== null) {
            return true;
        }
        // Bloqueada si vencio y no completada
        if ($task['due_date'] !== null && $task['status'] !== 'done') {
            $now = new DateTime();
            $due = new DateTime($task['due_date'] . ' 23:59:59');
            if ($now > $due) {
                return true;
            }
        }
        return false;
    }

    /** Verifica si un proyecto esta vencido (no se pueden crear tareas ni editar). */
    public static function isProjectExpired(int $projectId): bool
    {
        $stmt = db()->prepare('SELECT end_date FROM projects WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return true;
        }
        return new DateTime() > new DateTime($row['end_date'] . ' 23:59:59');
    }

    public static function forProject(int $projectId): array
    {
        $stmt = db()->prepare(
            'SELECT t.*, CONCAT(u.first_name, " ", u.last_name) AS student_name
             FROM project_tasks t LEFT JOIN users u ON u.id = t.assigned_student_id
             WHERE t.project_id = ? AND t.deleted_at IS NULL ORDER BY t.due_date IS NULL, t.due_date, t.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function create(int $projectId, array $data): void
    {
        $points = (int) ($data['points'] ?? 10);
        $points = max(1, min(100, $points));
        $priority = $data['priority'] ?? 'medium';
        if (!array_key_exists($priority, self::PRIORITIES)) {
            $priority = 'medium';
        }

        self::mutate($projectId, static function () use ($projectId, $data, $points, $priority): bool {
            $stmt = db()->prepare('INSERT INTO project_tasks (project_id,title,description,assigned_student_id,points,priority,status,due_date) VALUES(?,?,?,?,?,?,?,?)');
            $stmt->execute([$projectId,$data['title'],$data['description'] ?: null,$data['assigned_student_id'] ?: null,$points,$priority,$data['status'],$data['due_date'] ?: null]);
            return true;
        });
    }

    public static function updateStatus(int $taskId, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) throw new InvalidArgumentException('Estado invalido');
        $task = self::find($taskId);
        if (!$task) return false;
        return self::mutate((int) $task['project_id'], static function () use ($taskId, $status): bool {
            $stmt = db()->prepare('UPDATE project_tasks SET status=? WHERE id=? AND deleted_at IS NULL AND status<>?');
            $stmt->execute([$status,$taskId,$status]);
            return $stmt->rowCount() === 1;
        });
    }

    public static function gradeAndComplete(int $taskId, int $gradeResult, string $gradeComment): bool
    {
        $task = self::find($taskId);
        if (!$task) return false;
        return self::mutate((int) $task['project_id'], static function () use ($taskId, $gradeResult, $gradeComment): bool {
            $stmt = db()->prepare('UPDATE project_tasks SET status="done",grade_result=?,grade_comment=? WHERE id=? AND deleted_at IS NULL');
            $stmt->execute([$gradeResult,$gradeComment !== '' ? $gradeComment : null,$taskId]);
            return $stmt->rowCount() === 1;
        });
    }

    /** Solo el docente: actualiza nota y comentario sin cambiar el estado. */
    public static function updateGrade(int $taskId, int $gradeResult, string $gradeComment): bool
    {
        $stmt = db()->prepare(
            'UPDATE project_tasks SET grade_result = ?, grade_comment = ? WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$gradeResult, $gradeComment !== '' ? $gradeComment : null, $taskId]);
        return true;
    }

    public static function find(int $taskId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM project_tasks WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$taskId]);
        $task = $stmt->fetch();
        return $task === false ? null : $task;
    }

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT t.*, CONCAT(u.first_name, " ", u.last_name) AS student_name,
                    p.title AS project_title, p.id AS project_id
             FROM project_tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN users u ON u.id = t.assigned_student_id
             WHERE t.assigned_student_id = ? AND t.deleted_at IS NULL AND p.deleted_at IS NULL
             ORDER BY t.due_date IS NULL, t.due_date ASC, t.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function countsForUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT t.status, COUNT(*) AS total
             FROM project_tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.assigned_student_id = ? AND t.deleted_at IS NULL AND p.deleted_at IS NULL
             GROUP BY t.status'
        );
        $stmt->execute([$userId]);
        $counts = ['todo' => 0, 'doing' => 0, 'review' => 0, 'done' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function deleteTask(int $taskId): void
    {
        $task = self::find($taskId);
        if (!$task) return;
        self::mutate((int) $task['project_id'], static function () use ($taskId): bool {
            $stmt = db()->prepare('UPDATE project_tasks SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL');
            $stmt->execute([$taskId]);
            return $stmt->rowCount() === 1;
        });
    }

    private static function mutate(int $projectId, callable $operation): bool
    {
        $pdo = db();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id FROM projects WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$projectId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Proyecto no disponible');
            $changed = $operation();
            if ($changed) ProjectMetrics::record($projectId);
            if ($ownsTransaction) $pdo->commit();
            return $changed;
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
