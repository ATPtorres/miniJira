<?php
/** Controlador para subida y eliminación de archivos adjuntos. */

declare(strict_types=1);

final class FileController
{
    /** Recursos del docente o entregables de la tarea asignada al estudiante. */
    public static function canUpload(int $projectId, ?int $taskId = null): bool
    {
        if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
            return false;
        }
        $task = $taskId !== null ? Task::find($taskId) : null;
        if ($taskId !== null && ($task === null || (int) $task['project_id'] !== $projectId)) {
            return false;
        }
        if (can('files.upload') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            return true;
        }
        return $task !== null && can('tasks.update.assigned')
            && (int) $task['assigned_student_id'] === current_user_id()
            && !Task::isLocked($taskId) && !Task::isProjectExpired($projectId);
    }

    public static function upload(int $projectId, ?int $expectedTaskId = null): void
    {
        if (!is_post()) {
            redirect('projects/files.php?id=' . $projectId);
        }
        requireCsrf();
        $taskId = (int) post('task_id');
        if (($expectedTaskId !== null && $taskId !== $expectedTaskId)
            || !self::canUpload($projectId, $taskId > 0 ? $taskId : null)) {
            access_denied();
        }
        $returnTo = $expectedTaskId !== null
            ? 'projects/task.php?project_id=' . $projectId . '&task_id=' . $expectedTaskId
            : 'projects/files.php?id=' . $projectId;

        $files = $_FILES['files'] ?? null;
        if (!is_array($files) || !isset($files['name']) || !is_array($files['name']) || $files['name'] === []) {
            flash_set('error', 'No se seleccionaron archivos.');
            redirect($returnTo);
        }

        $category = post('category') ?: 'resource';
        $description = trim(post('description'));
        if (mb_strlen($description) > 500) {
            flash_set('error', 'La descripción no puede superar 500 caracteres.');
            redirect($returnTo);
        }

        if (!array_key_exists($category, ProjectFile::CATEGORIES)) {
            $category = 'resource';
        }

        // Validar el lote completo antes de guardar una entrega parcial.
        $validated = [];
        $batchSize = 0;
        foreach ($files['name'] as $i => $name) {
            $tmpName = $files['tmp_name'][$i] ?? null;
            $error = $files['error'][$i] ?? null;
            if (!is_string($name) || !is_string($tmpName) || !is_int($error)) {
                flash_set('error', 'La solicitud de archivos no es válida.');
                redirect($returnTo);
            }
            $name = basename(str_replace('\\', '/', $name));

            if ($error !== UPLOAD_ERR_OK) {
                flash_set('error', $error === UPLOAD_ERR_NO_FILE ? 'No se seleccionaron archivos.' : 'No se pudo recibir el archivo "' . $name . '". Máximo 10 MB por archivo.');
                redirect($returnTo);
            }
            if (!is_uploaded_file($tmpName) || $name === '' || mb_strlen($name) > 255) {
                flash_set('error', 'El archivo recibido no es válido.');
                redirect($returnTo);
            }
            $size = filesize($tmpName);
            if ($size === false || $size > ProjectFile::MAX_FILE_SIZE) {
                flash_set('error', 'El archivo "' . $name . '" supera el límite de 10 MB.');
                redirect($returnTo);
            }
            $batchSize += $size;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mimeType = mime_content_type($tmpName);
            if ($mimeType === false) {
                $mimeType = 'application/octet-stream';
            }

            if (!ProjectFile::isValidType($mimeType, $ext)) {
                flash_set('error', 'Tipo de archivo no permitido: "' . $name . '".');
                redirect($returnTo);
            }
            $validated[] = compact('name', 'tmpName', 'size', 'mimeType', 'ext');
        }

        $projectDir = ProjectFile::projectDir($projectId);
        $savedPaths = [];
        $db = db();
        $db->beginTransaction();
        try {
            // Serializar subidas del proyecto para respetar el cupo total.
            $lock = $db->prepare('SELECT id FROM projects WHERE id = ? FOR UPDATE');
            $lock->execute([$projectId]);
            if (!self::canUpload($projectId, $taskId > 0 ? $taskId : null)) {
                $db->rollBack();
                access_denied();
            }
            if (ProjectFile::totalSize($projectId) + $batchSize > ProjectFile::MAX_PROJECT_SIZE) {
                throw new RuntimeException('Se excede el límite total de 50 MB por proyecto.');
            }
            if (!is_dir($projectDir) && !mkdir($projectDir, 0755, true) && !is_dir($projectDir)) {
                throw new RuntimeException('No se pudo crear la carpeta de entregas.');
            }
            foreach ($validated as $file) {
                $storedName = bin2hex(random_bytes(16)) . '.' . $file['ext'];
                $dest = $projectDir . '/' . $storedName;
                if (!move_uploaded_file($file['tmpName'], $dest)) {
                    throw new RuntimeException('No se pudo guardar la entrega. Inténtalo de nuevo.');
                }
                $savedPaths[] = $dest;
                ProjectFile::create([
                    'project_id' => $projectId,
                    'task_id' => $taskId > 0 ? $taskId : null,
                    'uploaded_by_id' => current_user_id(),
                    'original_name' => $file['name'],
                    'stored_name' => $storedName,
                    'mime_type' => $file['mimeType'],
                    'file_size' => $file['size'],
                    'description' => $description,
                    'category' => $category,
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            foreach ($savedPaths as $path) unlink($path);
            error_log('Error guardando entrega: ' . $e->getMessage());
            flash_set('error', $e instanceof PDOException ? 'No se pudo registrar la entrega. Inténtalo de nuevo.' : $e->getMessage());
            redirect($returnTo);
        }

        flash_set('success', count($validated) . ' archivo(s) subido(s) correctamente.');

        $me = current_user();
        $uploaderName = $me['first_name'] . ' ' . $me['last_name'];
        $project = Project::find($projectId);
        $students = Project::students($projectId);
        $teacherId = (int) $project['responsible_teacher_id'];
        $link = $taskId > 0 ? 'projects/task.php?project_id=' . $projectId . '&task_id=' . $taskId : $returnTo;

        $notifyUserIds = [];
        if ($teacherId !== (int) $me['id']) {
            $notifyUserIds[] = $teacherId;
        }
        foreach ($students as $s) {
            if ((int) $s['id'] !== (int) $me['id']) {
                $notifyUserIds[] = (int) $s['id'];
            }
        }
        $notifyUserIds = array_unique($notifyUserIds);

        foreach ($notifyUserIds as $uid) {
            Notification::create(
                $uid,
                'file_uploaded',
                'Nuevo archivo subido',
                $uploaderName . ' subio un archivo al proyecto "' . $project['title'] . '"',
                $link
            );
        }

        redirect($returnTo);
    }

    public static function delete(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/files.php?id=' . $projectId);
        }
        requireCsrf();
        requirePermission('files.delete');
        if (!Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }

        $fileId = (int) post('file_id');
        $file = ProjectFile::find($fileId);
        if ($file === null || (int) $file['project_id'] !== $projectId) {
            flash_set('error', 'El archivo no es válido.');
            redirect('projects/files.php?id=' . $projectId);
        }

        // Eliminar archivo físico
        $path = ProjectFile::projectDir($projectId) . '/' . $file['stored_name'];
        if (file_exists($path)) {
            unlink($path);
        }

        ProjectFile::delete($fileId);
        flash_set('success', 'Archivo eliminado correctamente.');
        redirect('projects/files.php?id=' . $projectId);
    }
}
