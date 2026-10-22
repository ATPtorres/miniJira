<?php
/** Archivos adjuntos de proyectos y tareas. */

declare(strict_types=1);

final class ProjectFile
{
    public const CATEGORIES = [
        'resource'    => 'Recurso',
        'template'    => 'Plantilla',
        'instruction' => 'Instruccion',
        'format'      => 'Formato',
        'other'       => 'Otro',
    ];

    public const ALLOWED_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/zip',
        'application/x-rar-compressed',
        'text/plain',
        'video/mp4',
        'video/webm',
        'audio/mpeg',
        'audio/mp4',
        'audio/x-m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'audio/webm',
        'application/ogg',
    ];

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip', 'rar', 'txt',
        'mp4', 'webm', 'mp3', 'm4a', 'wav', 'ogg',
    ];

    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB
    public const MAX_PROJECT_SIZE = 50 * 1024 * 1024; // 50 MB total por proyecto

    /** Archivos de un proyecto, opcionalmente filtrados por tarea. Excluye adjuntos de comentarios. */
    public static function forProject(int $projectId, ?int $taskId = null): array
    {
        $sql = 'SELECT f.*, CONCAT(u.first_name, " ", u.last_name) AS uploader_name,
                       t.title AS task_title
                FROM project_files f
                JOIN users u ON u.id = f.uploaded_by_id
                LEFT JOIN project_tasks t ON t.id = f.task_id
                WHERE f.project_id = ? AND f.deleted_at IS NULL AND f.comment_id IS NULL
                  AND NOT EXISTS (SELECT 1 FROM project_files newer
                    WHERE newer.version_root_id=COALESCE(f.version_root_id,f.id)
                      AND newer.version_number>f.version_number AND newer.deleted_at IS NULL)';
        $params = [$projectId];
        if ($taskId !== null) {
            $sql .= ' AND f.task_id = ?';
            $params[] = $taskId;
        }
        $sql .= ' ORDER BY f.created_at DESC, f.id DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Adjuntos de un comentario. */
    public static function forComment(int $commentId): array
    {
        $stmt = db()->prepare(
            'SELECT f.*, CONCAT(u.first_name, " ", u.last_name) AS uploader_name
             FROM project_files f
             JOIN users u ON u.id = f.uploaded_by_id
             WHERE f.comment_id = ? AND f.deleted_at IS NULL
             ORDER BY f.created_at ASC'
        );
        $stmt->execute([$commentId]);
        return $stmt->fetchAll();
    }

    public static function forComments(array $ids): array
    {
        $ids=array_map('intval',$ids);if(!$ids)return [];
        $q=db()->prepare('SELECT * FROM project_files WHERE comment_id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND deleted_at IS NULL ORDER BY created_at,id');$q->execute($ids);$result=[];
        foreach($q->fetchAll() as $file)$result[(int)$file['comment_id']][]=$file;
        return $result;
    }

    public static function find(int $fileId, bool $includeDeleted = false): ?array
    {
        $stmt = db()->prepare('SELECT * FROM project_files WHERE id = ?' . ($includeDeleted ? '' : ' AND deleted_at IS NULL'));
        $stmt->execute([$fileId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO project_files (project_id, task_id, comment_id, uploaded_by_id, original_name, stored_name, mime_type, file_size, description, category, version_root_id, version_number)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['project_id'],
            $data['task_id'] ?: null,
            $data['comment_id'] ?? null,
            $data['uploaded_by_id'],
            $data['original_name'],
            $data['stored_name'],
            $data['mime_type'],
            $data['file_size'],
            $data['description'] ?: null,
            $data['category'] ?? 'resource',
            $data['version_root_id'] ?? null,
            $data['version_number'] ?? 1,
        ]);
        return (int) db()->lastInsertId();
    }

    public static function delete(int $fileId): void
    {
        $stmt = db()->prepare('UPDATE project_files SET deleted_at = NOW(), deleted_by_id = ? WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([current_user_id(), $fileId]);
    }

    public static function isLatest(array $file): bool
    {
        $stmt = db()->prepare('SELECT COUNT(*) FROM project_files WHERE version_root_id=? AND version_number>?');
        $stmt->execute([$file['version_root_id'] ?? $file['id'], $file['version_number']]);
        return (int) $stmt->fetchColumn() === 0;
    }

    public static function series(array $file): array
    {
        $rootId = $file['version_root_id'] ?? $file['id'];
        $stmt = db()->prepare('SELECT * FROM project_files WHERE id=? OR version_root_id=? ORDER BY version_number');
        $stmt->execute([$rootId, $rootId]);
        return $stmt->fetchAll();
    }

    /** Historial de entregables y recursos; los adjuntos de comentarios se consultan en su hilo. */
    public static function history(int $projectId, ?int $taskId, ?int $rootId, int $page = 1): array
    {
        $where = 'f.project_id=? AND f.comment_id IS NULL';
        $params = [$projectId];
        if ($taskId !== null) { $where .= ' AND f.task_id=?'; $params[] = $taskId; }
        if ($rootId !== null) { $where .= ' AND (f.id=? OR f.version_root_id=?)'; $params[] = $rootId; $params[] = $rootId; }
        $count = db()->prepare('SELECT COUNT(*) FROM project_files f WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));
        $stmt = db()->prepare('SELECT f.*, CONCAT(u.first_name," ",u.last_name) AS uploader_name,
            CONCAT(d.first_name," ",d.last_name) AS deleted_by_name, t.title AS task_title,
            NOT EXISTS (SELECT 1 FROM project_files newer WHERE newer.version_root_id=COALESCE(f.version_root_id,f.id)
                AND newer.version_number>f.version_number) AS is_latest
            FROM project_files f JOIN users u ON u.id=f.uploaded_by_id
            LEFT JOIN users d ON d.id=f.deleted_by_id LEFT JOIN project_tasks t ON t.id=f.task_id
            WHERE ' . $where . ' ORDER BY f.created_at DESC, f.id DESC LIMIT 50 OFFSET ' . (($page-1)*50));
        $stmt->execute($params);
        return ['items' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** Bytes totales usados por un proyecto. */
    public static function totalSize(int $projectId): int
    {
        $stmt = db()->prepare('SELECT COALESCE(SUM(file_size), 0) AS total FROM project_files WHERE project_id = ? AND deleted_at IS NULL');
        $stmt->execute([$projectId]);
        return (int) $stmt->fetch()['total'];
    }

    /** Tamaño en formato legible. */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /** Icono Font Awesome según tipo MIME. */
    public static function iconFor(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'pdf') => 'file-pdf',
            str_contains($mimeType, 'word') || str_contains($mimeType, 'document') => 'file-word',
            str_contains($mimeType, 'presentation') => 'file-powerpoint',
            str_contains($mimeType, 'sheet') || str_contains($mimeType, 'excel') => 'file-excel',
            str_contains($mimeType, 'image') => 'file-image',
            str_contains($mimeType, 'video') => 'file-video',
            str_contains($mimeType, 'audio') => 'file-audio',
            str_contains($mimeType, 'zip') || str_contains($mimeType, 'rar') => 'file-zipper',
            str_contains($mimeType, 'text') => 'file-lines',
            default => 'file',
        };
    }

    /** Ruta en disco para un proyecto. */
    public static function projectDir(int $projectId): string
    {
        return APP_PATH . '/storage/uploads/projects/' . $projectId;
    }

    /** Valida que el tipo MIME sea permitido. */
    public static function isValidType(string $mimeType, string $extension): bool
    {
        return in_array($mimeType, self::ALLOWED_TYPES, true)
            && in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true);
    }
}
