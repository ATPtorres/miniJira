<?php declare(strict_types=1); ?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><style>
body{font-family:"DejaVu Sans",sans-serif;font-size:10px;color:#172940} h1{font-size:20px}h2{font-size:14px;margin-top:22px}table{width:100%;border-collapse:collapse}td,th{padding:6px;border-bottom:1px solid #ddd;text-align:left;overflow-wrap:break-word}th{background:#eef2f6}tr{page-break-inside:avoid}.track{background:#e5e9ef;height:12px}.bar{background:#2874a6;height:12px}.muted{color:#586574}.description{white-space:normal}
</style></head><body>
<h1><?= e($project['title']) ?></h1>
<p><strong><?= $metrics['completed']?'Informe final':'Informe de avance' ?></strong> · Generado: <?= e(date('d/m/Y H:i T')) ?></p>
<p>Responsable: <?= e($project['teacher_name']) ?> · Periodo: <?= e($project['start_date']) ?> — <?= e($project['end_date']) ?></p>
<h2>Descripcion y objetivos</h2><div class="description"><?= nl2br(e($project['description'])) ?></div><p><?= nl2br(e($project['objectives'])) ?></p>
<h2>Avance: <?= $metrics['progress'] ?>%</h2><div class="track"><div class="bar" style="width:<?= $metrics['progress'] ?>%"></div></div>
<p><?= $metrics['done'] ?> de <?= $metrics['total'] ?> tareas terminadas. Todas las tareas tienen el mismo peso.</p>
<table><thead><tr><th>Estado</th><th>Cantidad</th><th>Distribucion</th></tr></thead><tbody>
<?php foreach(ProjectMetrics::LABELS as $status=>$label): ?><tr><td><?= e($label) ?></td><td><?= $metrics[$status] ?></td><td><div class="track"><div class="bar" style="width:<?= ProjectMetrics::percentage($metrics[$status],$metrics['total']) ?>%"></div></div></td></tr><?php endforeach; ?>
</tbody></table>
<h2>Avance por estudiante</h2><table><thead><tr><th>Estudiante</th><th>Terminadas / total</th><th>Avance</th><th>Puntos obtenidos / asignados</th></tr></thead><tbody>
<?php foreach($students as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= (int)$s['done'] ?> / <?= (int)$s['total'] ?></td><td><?= $s['progress'] ?>%</td><td><?= (int)$s['earned'] ?> / <?= (int)$s['points'] ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2>Detalle de tareas</h2><table><thead><tr><th>Tarea</th><th>Asignada a</th><th>Estado</th><th>Vencimiento</th><th>Nota / puntos</th></tr></thead><tbody>
<?php foreach($tasks as $task): ?><tr><td><?= e($task['title']) ?></td><td><?= e($task['student_name'] ?? 'Sin asignar') ?></td><td><?= e(ProjectMetrics::LABELS[$task['status']]) ?></td><td><?= e($task['due_date'] ?? 'Sin fecha') ?></td><td><?= $task['grade_result']===null?'Pendiente':(int)$task['grade_result'] ?> / <?= (int)$task['points'] ?></td></tr><?php endforeach; ?>
</tbody></table><p class="muted">El informe refleja el estado actual al generarlo. Se considera final cuando todas las tareas estan terminadas. No incluye comentarios privados, anonimos ni sus adjuntos.</p>
</body></html>
