<?php
/**
 * Sistema Universitario
 * app/helpers/notification_cron.php
 *
 * Recordatorios de fecha limite de tareas.
 * Ejecutar diariamente via cron job o manualmente desde CLI:
 *
 *   php app/helpers/notification_cron.php
 *
 * Busca tareas cuya fecha de vencimiento es manana y que aun no estan
 * completadas ni en revision, y crea una notificacion para el estudiante
 * asignado.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$tasks = db()->prepare(
    'SELECT t.id, t.title, t.due_date, t.assigned_student_id,
            p.title AS project_title, p.id AS project_id
     FROM project_tasks t
     JOIN projects p ON p.id = t.project_id
     WHERE t.due_date = CURDATE() + INTERVAL 1 DAY
       AND t.status NOT IN ("done", "review")
       AND t.assigned_student_id IS NOT NULL
       AND t.deleted_at IS NULL
       AND p.deleted_at IS NULL'
);
$tasks->execute();
$upcoming = $tasks->fetchAll();

$count = 0;
foreach ($upcoming as $task) {
    Notification::create(
        (int) $task['assigned_student_id'],
        'task_deadline_reminder',
        'Recordatorio: tarea vence manana',
        'La tarea "' . $task['title'] . '" del proyecto "' . $task['project_title'] . '" vence manana (' . date('d/m/Y', strtotime($task['due_date'])) . ').',
        'projects/board.php?id=' . $task['project_id']
    );
    $count++;
}

echo "Notificaciones de recordatorio creadas: " . $count . "\n";
