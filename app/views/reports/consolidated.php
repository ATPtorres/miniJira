<h1>Reporte de avance general y desempeño individual</h1>
<p>Generado: <?= e(date('d/m/Y H:i T')) ?>. Estado actual de los proyectos autorizados.</p>
<?php foreach(Workload::FIELDS as $key=>$label): if($data['filters'][$key]!==''): ?><p><?= e($label) ?>: <?= e($data['filters'][$key]) ?></p><?php endif; endforeach; ?>
<p><?= count($data['projects']) ?> proyectos · <?= $data['total']['done'] ?> / <?= $data['total']['total'] ?> tareas terminadas · Avance: <?= $data['total']['progress'] ?>%</p>
<p>El avance cuenta todas las tareas con el mismo peso. Desempeño = puntos obtenidos / puntos posibles de tareas evaluadas; las pendientes no se consideran cero. Vencidas: tareas sin terminar con fecha anterior a hoy.</p>
<?php if($data['filters']['classroom_id']!==''): ?><p>Clase: <?= e($data['filters']['classroom_id']) ?></p><?php endif; ?>
<?php if($data['student']!==''): ?><p>Alcance individual: solo tareas del estudiante seleccionado.</p><?php endif; ?>
<h2>Avance por proyecto</h2>
<table><thead><tr><th>Proyecto / contexto</th><th>Terminadas / total</th><th>Avance</th><th>Vencidas</th></tr></thead><tbody>
<?php foreach($data['projects'] as $r): ?><tr><td><?= e($r['title']) ?><br><?= e(implode(' · ',array_filter([$r['period'],$r['career'],$r['subject'],$r['group_name']]))) ?></td><td><?= $r['done'] ?> / <?= $r['total'] ?></td><td><?= $r['progress'] ?>%</td><td><?= $r['overdue'] ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2>Desempeño individual consolidado</h2>
<table><thead><tr><th>Estudiante</th><th>Proyectos</th><th>Terminadas / total</th><th>Avance</th><th>Evaluadas</th><th>Puntos obtenidos / evaluados</th><th>Desempeño</th><th>Vencidas</th></tr></thead><tbody>
<?php foreach($data['students'] as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= count($r['project_ids']) ?></td><td><?= $r['done'] ?> / <?= $r['total'] ?></td><td><?= $r['progress'] ?>%</td><td><?= $r['graded'] ?></td><td><?= $r['earned'] ?> / <?= $r['graded_points'] ?></td><td><?= $r['performance']===null?'Sin evaluación':$r['performance'].'%' ?></td><td><?= $r['overdue'] ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php if(!$data['projects']): ?><p>No hay resultados para estos filtros.</p><?php endif; ?>
