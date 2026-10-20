<?php
/**
 * Endpoint seguro de descarga de archivos.
 * GET: ?file_id=123
 * Valida autenticación + acceso al proyecto antes de servir el archivo.
 */

declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();

$fileId = (int) ($_GET['file_id'] ?? 0);
if ($fileId <= 0) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$file = ProjectFile::find($fileId);
if ($file === null) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

$projectId = (int) $file['project_id'];
if (!Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
    http_response_code(403);
    exit('Acceso denegado.');
}

// Adjunto de un comentario: ademas del acceso al proyecto se respeta su visibilidad.
if ($file['comment_id'] !== null) {
    $comment = ProjectComment::find((int) $file['comment_id']);
    if ($comment === null || !ProjectComment::isVisibleTo($comment, current_user_id(), can('projects.manage.all'))) {
        http_response_code(403);
        exit('Acceso denegado.');
    }
}

$path = ProjectFile::projectDir($projectId) . '/' . $file['stored_name'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor.');
}

// Servir el archivo
$originalName = $file['original_name'];
$mimeType = $file['mime_type'];
$fileSize = (int) $file['file_size'];

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $originalName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($path);
exit;
