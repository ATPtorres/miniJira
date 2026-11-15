<h1>Reporte de avance general y desempeño individual</h1>
<p>Generado: <?= e(date('d/m/Y H:i T')) ?>. Estado actual de los proyectos autorizados.</p>
<?php foreach(Workload::FIELDS as $key=>$label): if($data['filters'][$key]!==''): ?><p><?= e($label) ?>: <?= e($data['filters'][$key]) ?></p><?php endif; endforeach; ?>
<p><?= count($data['projects']) ?> proyectos · <?= $data['total']['done'] ?> / <?= $data['total']['total'] ?> tareas terminadas · Avance: <?= $data['total']['progress'] ?>%</p>
<p>El avance cuenta todas las tareas con el mismo peso. Desempeño = puntos obtenidos / puntos posibles de tareas evaluadas; las pendientes no se consideran cero. Vencidas: tareas sin terminar con fecha anterior a hoy.</p>
<?php if($data['filters']['classroom_id']!==''): ?><p>Clase: <?= e($data['filters']['classroom_id']) ?></p><?php endif; ?>
<?php if($data['student']!==''): ?><p>Alcance individual: solo tareas del estudiante seleccionado.</p><?php endif; ?>
<h2 id="report-projects-title">Avance por proyecto</h2>
<table class="report-table" role="table" aria-labelledby="report-projects-title">
<thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Proyecto / contexto</th><th scope="col" role="columnheader">Terminadas / total</th><th scope="col" role="columnheader">Avance</th><th scope="col" role="columnheader">Vencidas</th></tr></thead>
<tbody role="rowgroup">
<?php foreach($data['projects'] as $r): ?>
<tr role="row">
  <th scope="row" role="rowheader"><?= e($r['title']) ?><span class="report-context"><?= e(implode(' · ',array_filter([$r['period'],$r['career'],$r['subject'],$r['group_name']]))) ?></span></th>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Terminadas / total</span><?= $r['done'] ?> / <?= $r['total'] ?></td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Avance</span><?= $r['progress'] ?>%</td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Vencidas</span><?= $r['overdue'] ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<h2 id="report-students-title">Desempeño individual consolidado</h2>
<table class="report-table" role="table" aria-labelledby="report-students-title">
<thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Estudiante</th><th scope="col" role="columnheader">Proyectos</th><th scope="col" role="columnheader">Terminadas / total</th><th scope="col" role="columnheader">Avance</th><th scope="col" role="columnheader">Evaluadas</th><th scope="col" role="columnheader">Puntos obtenidos / evaluados</th><th scope="col" role="columnheader">Desempeño</th><th scope="col" role="columnheader">Vencidas</th></tr></thead>
<tbody role="rowgroup">
<?php foreach($data['students'] as $r): ?>
<tr role="row">
  <th scope="row" role="rowheader"><?= e($r['name']) ?></th>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Proyectos</span><?= count($r['project_ids']) ?></td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Terminadas / total</span><?= $r['done'] ?> / <?= $r['total'] ?></td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Avance</span><?= $r['progress'] ?>%</td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Evaluadas</span><?= $r['graded'] ?></td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Puntos obtenidos / evaluados</span><?= $r['earned'] ?> / <?= $r['graded_points'] ?></td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Desempeño</span><?= $r['performance']===null?'Sin evaluación':$r['performance'].'%' ?></td>
  <td role="cell"><span class="report-cell-label" aria-hidden="true">Vencidas</span><?= $r['overdue'] ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php if(!$data['projects']): ?><p>No hay resultados para estos filtros.</p><?php endif; ?>
