<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/bootstrap.php';
$pdo=db();
$pdo->exec(file_get_contents(__DIR__ . '/postgresql/beta.sql'));
require __DIR__ . '/migrations/20261021_file_history.php';
require __DIR__ . '/migrate_classrooms.php';
$pdo->exec("INSERT INTO project_progress_history(project_id,total_tasks,done_tasks)
 SELECT p.id,COUNT(t.id),COUNT(t.id) FILTER (WHERE t.status='done')
 FROM projects p LEFT JOIN project_tasks t ON t.project_id=p.id AND t.deleted_at IS NULL
 WHERE p.deleted_at IS NULL AND NOT EXISTS
 (SELECT 1 FROM project_progress_history h WHERE h.project_id=p.id)
 GROUP BY p.id ON CONFLICT DO NOTHING");
echo "Migracion Beta PostgreSQL aplicada; historial existente preservado.\n";
