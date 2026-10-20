<?php
/** Comentarios de proyectos: visibilidad general o privada, con anonimato opcional. */

declare(strict_types=1);

final class ProjectComment
{
    public const VISIBILITIES = ['project', 'private'];

    /** Maximo de comentarios raiz del docente por tarea. */
    public const MAX_ROOTS_PER_TASK = 10;

    /**
     * Regla de privacidad: un comentario "private" solo lo ven su autor,
     * el docente responsable del proyecto y quien tenga projects.manage.all.
     * El nombre del autor se oculta cuando el comentario es anonimo.
     * Solo comentarios de proyecto (task_id IS NULL); los de tarea van con forTask().
     */
    public static function forProject(int $projectId, int $viewerId, bool $globalAdmin): array
    {
        $sql = 'SELECT c.*, t.title AS task_title,
                       CASE WHEN c.is_anonymous = 1 THEN NULL
                            ELSE CONCAT(u.first_name, " ", u.last_name) END AS author_name,
                       r.code AS author_role
                FROM project_comments c
                JOIN users u ON u.id = c.author_id
                JOIN roles r ON r.id = u.role_id
                LEFT JOIN project_tasks t ON t.id = c.task_id
                WHERE c.project_id = ? AND c.deleted_at IS NULL AND c.task_id IS NULL
                  AND (
                    c.visibility = "project"
                    OR c.author_id = ?
                    OR ? = 1
                    OR ? = (SELECT p.responsible_teacher_id FROM projects p WHERE p.id = c.project_id)
                  )
                ORDER BY c.created_at ASC, c.id ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute([$projectId, $viewerId, $globalAdmin ? 1 : 0, $viewerId]);
        return $stmt->fetchAll();
    }

    /**
     * Comentarios de una tarea: cada raiz seguida de sus respuestas.
     * El acceso a la tarea (docente o estudiante asignado) ya esta verificado.
     */
    public static function forTask(int $taskId): array
    {
        $stmt = db()->prepare(
            'SELECT c.*, CONCAT(u.first_name, " ", u.last_name) AS author_name, r.code AS author_role
             FROM project_comments c
             JOIN users u ON u.id = c.author_id
             JOIN roles r ON r.id = u.role_id
             WHERE c.task_id = ? AND c.deleted_at IS NULL
             ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.created_at, c.id'
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    /** Cuantos comentarios raiz (los del docente) tiene la tarea. */
    public static function countRoots(int $taskId): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM project_comments
             WHERE task_id = ? AND parent_id IS NULL AND deleted_at IS NULL'
        );
        $stmt->execute([$taskId]);
        return (int) $stmt->fetchColumn();
    }

    public static function find(int $commentId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM project_comments WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$commentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $visibility = in_array($data['visibility'] ?? 'project', self::VISIBILITIES, true)
            ? $data['visibility']
            : 'project';
        $stmt = db()->prepare(
            'INSERT INTO project_comments (project_id, task_id, parent_id, author_id, body, is_anonymous, visibility)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['task_id'] ?? null,
            $data['parent_id'] ?? null,
            $data['author_id'],
            $data['body'],
            !empty($data['is_anonymous']) ? 1 : 0,
            $visibility,
        ]);
        return (int) db()->lastInsertId();
    }

    /** Eliminacion logica (los adjuntos se eliminan aparte; las respuestas de una raiz caen con ella). */
    public static function delete(int $commentId): void
    {
        $stmt = db()->prepare(
            'UPDATE project_comments SET deleted_at = NOW()
             WHERE deleted_at IS NULL AND (id = ? OR parent_id = ?)'
        );
        $stmt->execute([$commentId, $commentId]);
    }

    /** Puede el usuario ver este comentario? (el acceso al proyecto ya se verifico). */
    public static function isVisibleTo(array $comment, int $viewerId, bool $globalAdmin): bool
    {
        if (($comment['visibility'] ?? 'project') === 'project') {
            return true;
        }
        if ((int) $comment['author_id'] === $viewerId || $globalAdmin) {
            return true;
        }
        $stmt = db()->prepare('SELECT responsible_teacher_id FROM projects WHERE id = ?');
        $stmt->execute([(int) $comment['project_id']]);
        $teacherId = (int) $stmt->fetchColumn();
        return $teacherId > 0 && $teacherId === $viewerId;
    }
}
