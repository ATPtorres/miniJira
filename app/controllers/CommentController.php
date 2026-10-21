<?php
/** Conversaciones de proyectos y tareas, con adjuntos transaccionales. */
declare(strict_types=1);

final class CommentController
{
    public static function store(int $projectId): void
    {
        self::storeComment($projectId, null);
    }

    public static function storeTask(int $projectId, int $taskId): void
    {
        self::storeComment($projectId, $taskId);
    }

    private static function storeComment(int $projectId, ?int $taskId): void
    {
        $back = self::location($projectId, $taskId);
        if (!is_post()) redirect($back);
        requireCsrf();
        if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) access_denied();
        $body = trim(post('body'));
        if ($body === '' || mb_strlen($body) > 2000) {
            flash_set('error', 'El comentario debe tener entre 1 y 2000 caracteres.');
            redirect($back);
        }
        $parentId = max(0, (int) post('parent_id'));
        $visibility = in_array(post('visibility'), ProjectComment::VISIBILITIES, true) ? post('visibility') : 'project';
        $anonymous = $taskId === null && post('is_anonymous') === '1';
        $savedPaths = [];
        $db = db();
        try {
            $pending = CommentAttachments::validate($_FILES['files'] ?? null);
            $db->beginTransaction();
            // El mismo candado que tareas y entregas: cupo y limites consistentes.
            $lock = $db->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
            $lock->execute([$projectId]);
            if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
                $db->rollBack();
                access_denied();
            }
            if ($taskId !== null) {
                $task = Task::find($taskId);
                if ($task === null || (int) $task['project_id'] !== $projectId) {
                    throw new InvalidArgumentException('La tarea no es válida.');
                }
                $visibility = 'project';
            }
            if ($parentId > 0) {
                $parent = ProjectComment::find($parentId);
                if ($parent === null || (int) $parent['project_id'] !== $projectId
                    || ($parent['task_id'] === null ? null : (int) $parent['task_id']) !== $taskId
                    || $parent['parent_id'] !== null
                    || !ProjectComment::isVisibleTo($parent, current_user_id(), can('projects.manage.all'))) {
                    throw new InvalidArgumentException('El hilo al que respondes no está disponible.');
                }
                // Una respuesta nunca puede convertir un hilo privado en público.
                $visibility = $parent['visibility'];
                if ((int) $parent['is_anonymous'] === 1 && (int) $parent['author_id'] === current_user_id()) {
                    $anonymous = true;
                }
            } elseif ($taskId !== null) {
                $canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
                if (!$canManage) throw new InvalidArgumentException('Solo el docente puede abrir un comentario en la tarea. Usa "Responder".');
                if (ProjectComment::countRoots($taskId) >= ProjectComment::MAX_ROOTS_PER_TASK) {
                    throw new InvalidArgumentException('Límite de comentarios alcanzado en esta tarea.');
                }
            }
            $commentId = ProjectComment::create([
                'project_id' => $projectId, 'task_id' => $taskId,
                'parent_id' => $parentId ?: null, 'author_id' => current_user_id(),
                'body' => $body, 'is_anonymous' => $anonymous, 'visibility' => $visibility,
            ]);
            CommentAttachments::save($projectId, $taskId, $commentId, $pending, $savedPaths);
            // El aviso se confirma junto con el comentario y sus archivos.
            if ($taskId !== null) self::notifyTask($projectId, $taskId, $parentId > 0, $body);
            else self::notify($projectId, $commentId, $anonymous, $visibility, $body);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            foreach ($savedPaths as $path) {
                if (is_file($path)) unlink($path);
            }
            error_log('Error publicando comentario: ' . $e->getMessage());
            flash_set('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'No se pudo publicar el comentario y sus adjuntos. Inténtalo de nuevo.');
            redirect($back);
        }
        flash_set('success', $parentId > 0 ? 'Respuesta publicada.' : 'Comentario publicado.');
        redirect($back);
    }

    public static function delete(int $projectId): void
    {
        self::deleteComment($projectId, null);
    }

    public static function deleteTask(int $projectId, int $taskId): void
    {
        self::deleteComment($projectId, $taskId);
    }

    private static function deleteComment(int $projectId, ?int $taskId): void
    {
        $back = self::location($projectId, $taskId);
        if (!is_post()) redirect($back);
        requireCsrf();
        if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) access_denied();
        $db = db();
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
            $lock->execute([$projectId]);
            if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
                $db->rollBack();
                access_denied();
            }
            $commentId = (int) post('comment_id');
            $comment = ProjectComment::find($commentId);
            $canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
            if ($comment === null || (int) $comment['project_id'] !== $projectId
                || ($comment['task_id'] === null ? null : (int) $comment['task_id']) !== $taskId
                || !ProjectComment::isVisibleTo($comment, current_user_id(), can('projects.manage.all'))
                || ((int) $comment['author_id'] !== current_user_id() && !$canManage)) {
                $db->rollBack();
                access_denied();
            }
            $stmt = $db->prepare('SELECT f.* FROM project_files f JOIN project_comments c ON c.id=f.comment_id
                WHERE f.project_id=? AND f.deleted_at IS NULL AND (c.id=? OR c.parent_id=?)');
            $stmt->execute([$projectId, $commentId, $commentId]);
            $files = $stmt->fetchAll();
            foreach ($files as $file) ProjectFile::delete((int) $file['id']);
            ProjectComment::delete($commentId);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Error eliminando comentario: ' . $e->getMessage());
            flash_set('error', 'No se pudo eliminar el comentario. Inténtalo de nuevo.');
            redirect($back);
        }
        // Primero revocar el acceso en la BD; un fallo de limpieza no expone archivos.
        foreach ($files as $file) {
            $path = ProjectFile::projectDir($projectId) . '/' . $file['stored_name'];
            if (is_file($path) && !@unlink($path)) error_log('No se pudo limpiar adjunto eliminado #' . $file['id']);
        }
        flash_set('success', 'Comentario y adjuntos eliminados.');
        redirect($back);
    }

    private static function location(int $projectId, ?int $taskId): string
    {
        return $taskId === null ? 'projects/dashboard.php?id=' . $projectId
            : 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId;
    }

    /** Notifica a la otra parte de la conversacion de la tarea (raiz -> estudiante; respuesta -> la otra parte). */
    private static function notifyTask(int $projectId, int $taskId, bool $isReply, string $body): void
    {
        $me = current_user();
        $authorId = (int) $me['id'];
        $authorName = $me['first_name'] . ' ' . $me['last_name'];
        $task = Task::find($taskId);
        $project = Project::find($projectId);
        $assignedId = (int) $task['assigned_student_id'];
        $teacherId = (int) $project['responsible_teacher_id'];
        $excerpt = mb_substr((string) preg_replace('/\s+/', ' ', $body), 0, 80);
        $link = 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId;

        $targets = [];
        if (!$isReply) {
            // Raiz (docente) -> estudiante asignado.
            if ($assignedId > 0 && $assignedId !== $authorId) {
                $targets[] = $assignedId;
            }
            $message = $authorName . ' comento en la tarea "' . $task['title'] . '": ' . $excerpt;
        } else {
            // Respuesta -> siempre se avisa al docente responsable; si el
            // autor no es el estudiante asignado, tambien a el.
            if ($teacherId !== $authorId) {
                $targets[] = $teacherId;
            }
            if ($assignedId > 0 && $assignedId !== $authorId && $assignedId !== $teacherId) {
                $targets[] = $assignedId;
            }
            $message = $authorName . ' respondio en la tarea "' . $task['title'] . '": ' . $excerpt;
        }

        foreach (array_unique($targets) as $uid) {
            if (!Project::userCanAccess($projectId, $uid)) continue;
            Notification::create($uid, 'comment_added', 'Nuevo comentario en tarea', $message, $link);
        }
    }

    /**
     * Notifica segun visibilidad:
     * - privado: solo al docente responsable (si no es el autor);
     * - general escrita por docente/admin: a los estudiantes asignados;
     * - general escrita por estudiante: al docente responsable.
     * En comentarios anonimos el mensaje nunca revela el autor.
     */
    private static function notify(int $projectId, int $commentId, bool $anonymous, string $visibility, string $body): void
    {
        $me = current_user();
        $authorId = (int) $me['id'];
        $authorName = $me['first_name'] . ' ' . $me['last_name'];
        $project = Project::find($projectId);
        $teacherId = (int) $project['responsible_teacher_id'];
        $link = 'projects/dashboard.php?id=' . $projectId . '#comentarios';
        $excerpt = mb_substr((string) preg_replace('/\s+/', ' ', $body), 0, 80);

        $notifyUserIds = [];
        $comment = ProjectComment::find($commentId);
        if ($comment['parent_id'] !== null) {
            $root = ProjectComment::find((int) $comment['parent_id']);
            $notifyUserIds[] = (int) $root['author_id'];
        }
        if ($visibility === 'private') {
            if ($teacherId !== $authorId) {
                $notifyUserIds[] = $teacherId;
            }
        } elseif ($authorId === $teacherId || can('projects.manage.all')) {
            foreach (Project::students($projectId) as $s) {
                if ((int) $s['id'] !== $authorId) {
                    $notifyUserIds[] = (int) $s['id'];
                }
            }
        } elseif ($teacherId !== $authorId) {
            $notifyUserIds[] = $teacherId;
        }

        $notifyUserIds = array_unique(array_filter($notifyUserIds));
        $who = $anonymous ? 'Un participante anonimo' : $authorName;

        foreach ($notifyUserIds as $uid) {
            if ($uid === $authorId || !Project::userCanAccess($projectId, $uid)
                || !ProjectComment::isVisibleTo($comment, $uid, false)) continue;
            Notification::create(
                $uid,
                'comment_added',
                'Nuevo comentario',
                $who . ' comento en el proyecto "' . $project['title'] . '": ' . $excerpt,
                $link
            );
        }
    }
}
