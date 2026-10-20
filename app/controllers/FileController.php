<?php
/** Controlador para subida y eliminación de archivos adjuntos. */

declare(strict_types=1);

final class FileController
{
    public static function upload(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/files.php?id=' . $projectId);
        }
        requireCsrf();
        requirePermission('files.upload');
        if (!Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }

        if (!isset($_FILES['files']) || $_FILES['files']['error'][0] === UPLOAD_ERR_NO_FILE) {
            flash_set('error', 'No se seleccionaron archivos.');
            redirect('projects/files.php?id=' . $projectId);
        }

        $taskId = (int) post('task_id');
        $category = post('category') ?: 'resource';
        $description = trim(post('description'));

        if (!array_key_exists($category, ProjectFile::CATEGORIES)) {
            $category = 'resource';
        }

        $projectDir = ProjectFile::projectDir($projectId);
        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }

        $totalSize = ProjectFile::totalSize($projectId);
        $uploaded = 0;
        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            $name = $files['name'][$i] ?? $files['name'];
            $tmpName = $files['tmp_name'][$i] ?? $files['tmp_name'];
            $error = $files['error'][$i] ?? $files['error'];
            $size = $files['size'][$i] ?? $files['size'];

            if ($error !== UPLOAD_ERR_OK) {
                flash_set('error', 'Error al subir "' . e($name) . '". Código de error: ' . $error);
                redirect('projects/files.php?id=' . $projectId);
            }

            if ($size > ProjectFile::MAX_FILE_SIZE) {
                flash_set('error', 'El archivo "' . e($name) . '" supera el límite de 10 MB.');
                redirect('projects/files.php?id=' . $projectId);
            }

            $totalSize += $size;
            if ($totalSize > ProjectFile::MAX_PROJECT_SIZE) {
                flash_set('error', 'Se excede el límite total de 50 MB por proyecto.');
                redirect('projects/files.php?id=' . $projectId);
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mimeType = mime_content_type($tmpName);
            if ($mimeType === false) {
                $mimeType = 'application/octet-stream';
            }

            if (!ProjectFile::isValidType($mimeType, $ext)) {
                flash_set('error', 'Tipo de archivo no permitido: "' . e($name) . '".');
                redirect('projects/files.php?id=' . $projectId);
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = $projectDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $dest)) {
                flash_set('error', 'Error al guardar el archivo "' . e($name) . '".');
                redirect('projects/files.php?id=' . $projectId);
            }

            ProjectFile::create([
                'project_id' => $projectId,
                'task_id' => $taskId > 0 ? $taskId : null,
                'uploaded_by_id' => current_user_id(),
                'original_name' => $name,
                'stored_name' => $storedName,
                'mime_type' => $mimeType,
                'file_size' => $size,
                'description' => $description,
                'category' => $category,
            ]);
            $uploaded++;
        }

        flash_set('success', $uploaded . ' archivo(s) subido(s) correctamente.');

        $me = current_user();
        $uploaderName = $me['first_name'] . ' ' . $me['last_name'];
        $project = Project::find($projectId);
        $students = Project::students($projectId);
        $teacherId = (int) $project['responsible_teacher_id'];
        $link = 'projects/files.php?id=' . $projectId;

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

        redirect('projects/files.php?id=' . $projectId);
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
