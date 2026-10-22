<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';
requireAuth();
$summaryRaw=Project::summaryFor(current_user_id(),can('projects.manage.all'));
$pages=max(1,(int)ceil($summaryRaw['projects']/25));$page=max(1,min((int)($_GET['page']??1),$pages));
$projects=Project::accessibleTo(current_user_id(),can('projects.manage.all'),25,($page-1)*25);
$projectId=(int)($_GET['id'] ?? 0);
$ids=array_map('intval',array_column($projects,'id'));
if ($projectId !== 0 && !Project::userCanAccess($projectId,current_user_id(),can('projects.manage.all'))) access_denied();
if($projectId && !in_array($projectId,$ids,true))$projects[]=Project::find($projectId);
$error='';
try { [$from,$to]=ProjectMetrics::dateRange($_GET['from'] ?? null,$_GET['to'] ?? null); }
catch (InvalidArgumentException $e) { $error=$e->getMessage(); [$from,$to]=ProjectMetrics::dateRange(null,null); }
$summary=ProjectMetrics::fromCounts($summaryRaw);
$pageTitle='Analitica de proyectos';
require APP_PATH.'/app/views/partials/header.php';
?>
<section class="panel-head"><h1>Analitica de proyectos</h1></section>
<?php if($error): ?><p class="alert alert-error"><?= e($error) ?></p><?php endif; ?>
<form method="get" class="card analytics-filters">
  <label>Proyecto<select name="id"><option value="0">Todos mis proyectos</option><?php foreach($projects as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$p['id']===$projectId?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select></label>
  <label>Desde<input type="date" name="from" value="<?= e($from) ?>" required></label>
  <label>Hasta<input type="date" name="to" value="<?= e($to) ?>" required></label>
  <button class="btn btn-primary">Aplicar filtros</button>
</form>
<?php if($projectId):
  $metrics=ProjectMetrics::forProject($projectId);
  require APP_PATH.'/app/views/partials/project-charts.php';
  $students=ProjectMetrics::students($projectId);
?>
<section class="card"><h2>Avance por estudiante</h2><table><thead><tr><th>Estudiante</th><th>Terminadas / total</th><th>Avance</th></tr></thead><tbody>
<?php foreach($students as $s): ?><tr data-live-student="<?= (int)$s['id'] ?>"><td><?= e($s['name']) ?></td><td><span data-student-done><?= (int)$s['done'] ?></span> / <span data-student-total><?= (int)$s['total'] ?></span></td><td><span data-student-progress><?= $s['progress'] ?></span>%</td></tr><?php endforeach; ?>
</tbody></table></section>
<?php else: ?>
<section class="card"><h2>Distribucion de tareas de mis proyectos</h2><div data-live-summary><?= ProjectCharts::states($summary) ?></div></section>
<section class="card"><h2>Avance por proyecto</h2>
<?php if(!$projects): ?><p>No hay proyectos disponibles.</p><?php endif; ?>
<?php foreach($projects as $p): $m=ProjectMetrics::fromCounts($p); ?>
<div class="progress-row" data-project-progress="<?= (int)$p['id'] ?>"><a href="<?= e(base_url('projects/analytics.php?id='.$p['id'].'&from='.$from.'&to='.$to)) ?>"><?= e($p['title']) ?></a><span><span data-live-value="progress"><?= $m['progress'] ?></span>%</span><div class="progress" style="grid-column:1/-1"><div class="progress-bar" data-live-bar style="width:<?= $m['progress'] ?>%"></div></div></div>
<?php endforeach; ?></section>
<?php endif; ?>
<?php if(!$projectId):?><nav><?php if($page>1):?><a href="?<?=e(http_build_query(['page'=>$page-1,'from'=>$from,'to'=>$to]))?>">Anterior</a><?php endif;?> Página <?=$page?> de <?=$pages?> <?php if($page<$pages):?><a href="?<?=e(http_build_query(['page'=>$page+1,'from'=>$from,'to'=>$to]))?>">Siguiente</a><?php endif;?></nav><?php endif;?>
<?php require APP_PATH.'/app/views/partials/footer.php'; ?>
