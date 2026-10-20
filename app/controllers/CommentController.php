<?php
/** Comentarios de proyectos: crear (con adjuntos multimedia) y eliminar. */

declare(strict_types=1);

final class CommentController
{
    /** POST: crea un comentario con visibilidad/anonimato y adjuntos opcionales. */
    public static function store(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/dashboard.php?id=' . $projectId);
        }
        requireCsrf();

        $body = trim((string) post('body'));
        if ($body === '') {
            flash_set('error', 'El comentario no puede estar vacio.');
            redirect('projects/dashboard.php?id=' . $projectId);
        }
        if (mb_strlen($body) > 2000) {
            flash_set('error', 'El comentario no puede superar los 2000 caracteres.');
            redirect('projects/dashboard.php?id=' . $projectId);
        }

        $visibility = (string) post('visibility');
        if (!in_array($visibility, ProjectComment::VISIBILITIES, true)) {
            $visibility = 'project';
        }
        $isAnonymous = post('is_anonymous') === '1' ? 1 : 0;

        // Validar todos los adjuntos ANTES de crear el comentario (todo o nada).
        $files = $_FILES['files'] ?? null;
        $pending = [];
        if ($files !== null && ($files['error'][0] ?? $files['error']) !== UPLOAD_ERR_NO_FILE) {
            $totalSize = ProjectFile::totalSize($projectId);
            $count = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $count; $i++) {
                $name = $files['name'][$i] ?? $files['name'];
                $tmpName = $files['tmp_name'][$i] ?? $files['tmp_name'];
                $error = $files['error'][$i] ?? $files['error'];
                $size = (int) ($files['size'][$i] ?? $files['size']);

                if ($error !== UPLOAD_ERR_OK) {
                    flash_set('error', 'Error al subir "' . e($name) . '".');
                    redirect('projects/dashboard.php?id=' . $projectId);
                }
                if ($size > ProjectFile::MAX_FILE_SIZE) {
                    flash_set('error', 'El archivo "' . e($name) . '" supera el limite de 10 MB.');
                    redirect('projects/dashboard.php?id=' . $projectId);
                }
                $totalSize += $size;
                if ($totalSize > ProjectFile::MAX_PROJECT_SIZE) {
                    flash_set('error', 'Se excede el limite total de 50 MB por proyecto.');
                    redirect('projects/dashboard.php?id=' . $projectId);
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $mimeType = mime_content_type($tmpName);
                if ($mimeType === false) {
                    $mimeType = 'application/octet-stream';
                }
                if (!ProjectFile::isValidType($mimeType, $ext)) {
                    flash_set('error', 'Tipo de archivo no permitido: "' . e($name) . '".');
                    redirect('projects/dashboard.php?id=' . $projectId);
                }

                $pending[] = [
                    'name' => $name,
                    'tmp'   => $tmpName,
                    'size'  => $size,
                    'ext'   => $ext,
                    'mime'  => $mimeType,
                ];
            }
        }

        $commentId = ProjectComment::create([
            'project_id'  => $projectId,
            'task_id'     => null,
            'author_id'   => current_user_id(),
            'body'        => $body,
            'is_anonymous' => $isAnonymous,
            'visibility'  => $visibility,
        ]);

        if ($pending !== []) {
            $projectDir = ProjectFile::projectDir($projectId);
            if (!is_dir($projectDir)) {
                mkdir($projectDir, 0755, true);
            }
            foreach ($pending as $p) {
                $storedName = bin2hex(random_bytes(16)) . '.' . $p['ext'];
                if (!move_uploaded_file($p['tmp'], $projectDir . '/' . $storedName)) {
                    flash_set('error', 'No se pudo guardar el adjunto "' . e($p['name']) . '".');
                    continue;
                }
                ProjectFile::create([
                    'project_id'    => $projectId,
                    'task_id'       => null,
                    'uploaded_by_id' => current_user_id(),
                    'original_name' => $p['name'],
                    'stored_name'   => $storedName,
                    'mime_type'     => $p['mime'],
                    'file_size'     => $p['size'],
                    'description'   => 'Adjunto de comentario #' . $commentId,
                    'category'      => 'other',
                    'comment_id'    => $commentId,
                ]);
            }
        }

        self::notify($projectId, $commentId, $isAnonymous === 1, $visibility, $body);

        flash_set('success', 'Comentario publicado.');
        redirect('projects/dashboard.php?id=' . $projectId);
    }

    /** POST: elimina (logico) un comentario y sus adjuntos. */
    public static function delete(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/dashboard.php?id=' . $projectId);
        }
        requireCsrf();

        $commentId = (int) post('comment_id');
        $comment = ProjectComment::find($commentId);
        $canManage = can('tasks.manage')
            && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));

        if ($comment === null || (int) $comment['project_id'] !== $projectId) {
            flash_set('error', 'El comentario no es valido.');
            redirect('projects/dashboard.php?id=' . $projectId);
        }
        if ((int) $comment['author_id'] !== current_user_id() && !$canManage) {
            access_denied();
        }

        foreach (ProjectFile::forComment($commentId) as $file) {
            $path = ProjectFile::projectDir($projectId) . '/' . $file['stored_name'];
            if (file_exists($path)) {
                unlink($path);
            }
            ProjectFile::delete((int) $file['id']);
        }

        ProjectComment::delete($commentId);
        flash_set('success', 'Comentario eliminado.');
        redirect('projects/dashboard.php?id=' . $projectId);
    }

    /** POST: crea un comentario en una tarea (raiz del docente o respuesta). */
    public static function storeTask(int $projectId, int $taskId): void
    {
        $back = 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId;
        if (!is_post()) {
            redirect($back);
        }
        requireCsrf();

        $body = trim((string) post('body'));
        if ($body === '') {
            flash_set('error', 'El comentario no puede estar vacio.');
            redirect($back);
        }
        if (mb_strlen($body) > 2000) {
            flash_set('error', 'El comentario no puede superar los 2000 caracteres.');
            redirect($back);
        }

        $canManage = can('tasks.manage')
            && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
        $task = Task::find($taskId);
        if ($task === null || (int) $task['project_id'] !== $projectId) {
            flash_set('error', 'La tarea no es valida.');
            redirect($back);
        }
        $isMember = Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'));
        if (!$canManage && !$isMember) {
            access_denied();
        }

        $parentId = (int) post('parent_id');

        if ($parentId <= 0) {
            // Raiz: solo el docente, con limite de comentarios por tarea.
            if (!$canManage) {
                flash_set('error', 'Solo el docente puede abrir un comentario en la tarea. Usa "Responder".');
                redirect($back);
            }
            if (ProjectComment::countRoots($taskId) >= ProjectComment::MAX_ROOTS_PER_TASK) {
                flash_set('error', 'Limite de ' . ProjectComment::MAX_ROOTS_PER_TASK . ' comentarios alcanzado en esta tarea.');
                redirect($back);
            }
        } else {
            $parent = ProjectComment::find($parentId);
            if ($parent === null || (int) $parent['task_id'] !== $taskId || (int) $parent['project_id'] !== $projectId) {
                flash_set('error', 'El comentario al que respondes no es valido.');
                redirect($back);
            }
            if ($parent['parent_id'] !== null) {
                flash_set('error', 'Responde directamente al comentario principal.');
                redirect($back);
            }
        }

        $commentId = ProjectComment::create([
            'project_id'  => $projectId,
            'task_id'     => $taskId,
            'parent_id'   => $parentId > 0 ? $parentId : null,
            'author_id'   => current_user_id(),
            'body'        => $body,
            'is_anonymous' => 0,
            'visibility'  => 'project',
        ]);

        self::notifyTask($projectId, $taskId, $parentId > 0, $body);

        flash_set('success', $parentId > 0 ? 'Respuesta publicada.' : 'Comentario publicado.');
        redirect($back);
    }

    /** POST: elimina un comentario de tarea (autor o gestor; una raiz borra sus respuestas). */
    public static function deleteTask(int $projectId, int $taskId): void
    {
        $back = 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId;
        if (!is_post()) {
            redirect($back);
        }
        requireCsrf();

        $canManage = can('tasks.manage')
            && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
        $commentId = (int) post('comment_id');
        $comment = ProjectComment::find($commentId);

        if ($comment === null || (int) $comment['project_id'] !== $projectId || (int) $comment['task_id'] !== $taskId) {
            flash_set('error', 'El comentario no es valido.');
            redirect($back);
        }
        if ((int) $comment['author_id'] !== current_user_id() && !$canManage) {
            access_denied();
        }

        ProjectComment::delete($commentId);
        flash_set('success', 'Comentario eliminado.');
        redirect($back);
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
