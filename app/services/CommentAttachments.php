<?php
/** Validación y persistencia de adjuntos; el controlador gestiona la transacción. */
declare(strict_types=1);

final class CommentAttachments
{
    public static function validate(?array $files): array
    {
        if ($files === null) return [];
        if (!isset($files['name']) || !is_array($files['name'])) {
            throw new InvalidArgumentException('La solicitud de archivos no es válida.');
        }
        $pending = [];
        foreach ($files['name'] as $i => $name) {
            $error = $files['error'][$i] ?? null;
            $tmp = $files['tmp_name'][$i] ?? null;
            if (!is_string($name) || !is_int($error) || !is_string($tmp)) {
                throw new InvalidArgumentException('La solicitud de archivos no es válida.');
            }
            if ($error === UPLOAD_ERR_NO_FILE && count($files['name']) === 1) return [];
            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('No se pudo recibir el adjunto. Máximo 10 MB por archivo.');
            }
            $name = basename(str_replace('\\', '/', $name));
            if (!is_uploaded_file($tmp) || $name === '' || mb_strlen($name) > 255) {
                throw new InvalidArgumentException('El archivo recibido no es válido.');
            }
            $size = filesize($tmp);
            if ($size === false || $size > ProjectFile::MAX_FILE_SIZE) {
                throw new InvalidArgumentException('El adjunto supera el límite de 10 MB.');
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = mime_content_type($tmp) ?: 'application/octet-stream';
            if (!ProjectFile::isValidType($mime, $ext)) {
                throw new InvalidArgumentException('Tipo de adjunto no permitido: ' . $name);
            }
            $pending[] = compact('name', 'tmp', 'size', 'ext', 'mime');
        }
        return $pending;
    }

    public static function save(int $projectId, ?int $taskId, int $commentId, array $pending, array &$savedPaths): void
    {
        if ($pending === []) return;
        if (ProjectFile::totalSize($projectId) + array_sum(array_column($pending, 'size')) > ProjectFile::MAX_PROJECT_SIZE) {
            throw new InvalidArgumentException('Se excede el límite total de 50 MB por proyecto.');
        }
        $dir = ProjectFile::projectDir($projectId);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de adjuntos.');
        }
        foreach ($pending as $file) {
            $storedName = bin2hex(random_bytes(16)) . '.' . $file['ext'];
            $path = $dir . '/' . $storedName;
            if (!move_uploaded_file($file['tmp'], $path)) throw new RuntimeException('No se pudo guardar el adjunto.');
            $savedPaths[] = $path;
            ProjectFile::create([
                'project_id' => $projectId, 'task_id' => $taskId, 'comment_id' => $commentId,
                'uploaded_by_id' => current_user_id(), 'original_name' => $file['name'],
                'stored_name' => $storedName, 'mime_type' => $file['mime'], 'file_size' => $file['size'],
                'description' => 'Adjunto de comentario #' . $commentId, 'category' => 'other',
            ]);
        }
    }
}
