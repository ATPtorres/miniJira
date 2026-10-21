<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/bootstrap.php';
$sql = file_get_contents(__DIR__ . '/migrations/20261021_beta.sql');
db()->exec($sql);
require __DIR__ . '/migrations/20261021_file_history.php';
// Seed one baseline only for projects without an observed history.
db()->exec('INSERT INTO project_progress_history (project_id, total_tasks, done_tasks)
    SELECT p.id, COUNT(t.id), COALESCE(SUM(t.status = "done"), 0)
    FROM projects p LEFT JOIN project_tasks t ON t.project_id = p.id AND t.deleted_at IS NULL
    WHERE p.deleted_at IS NULL AND NOT EXISTS
      (SELECT 1 FROM project_progress_history h WHERE h.project_id = p.id)
    GROUP BY p.id');
echo "Migracion Beta aplicada; historial existente preservado.\n";
