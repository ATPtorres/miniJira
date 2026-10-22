<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';requireAuth();requirePermission('user.access');
if(can('admin.access'))redirect('admin/dashboard.php');
$me=current_user();$admin=can('projects.manage.all');$own=$me['role_code']==='estudiante';
try{$filters=Workload::filters($_GET);}catch(InvalidArgumentException $e){http_response_code(400);exit('Filtros no válidos.');}
if($filters['classroom_id']!=='' && !Classroom::canAccess((int)$filters['classroom_id'],current_user_id(),$admin))access_denied();
$data=Workload::page(current_user_id(),$admin,$own,$filters,(int)($_GET['page']??1));
$options=Workload::options(current_user_id(),$admin,$filters);$filterReset='user/dashboard.php';
$pageTitle=$own?'Mis tareas y entregas':'Mi carga de trabajo';require APP_PATH.'/app/views/partials/header.php';?>
<section class="panel-head"><div><h1><?=e($pageTitle)?></h1><p><?=e($me['first_name'])?>, organiza el trabajo de tus clases autorizadas.</p></div><div class="form-actions"><a class="btn btn-outline-dark" href="<?=e(base_url('user/classes.php'))?>">Mis clases</a><a class="btn btn-outline-dark" href="<?=e(base_url('projects/index.php'))?>">Proyectos</a><?php if(can('projects.create')):?><a class="btn btn-primary" href="<?=e(base_url('projects/form.php'))?>">Crear proyecto</a><?php endif;?></div></section>
<?php require APP_PATH.'/app/views/partials/workload-filters.php';?>
<section class="cards"><?php foreach(['total'=>'Tareas del filtro','pending'=>'Pendientes','review'=>'Por evaluar','graded'=>'Evaluadas'] as $key=>$label):?><article class="card stat-card"><div><div class="stat-value"><?=$data['counts'][$key]?></div><p><?=e($label)?></p></div></article><?php endforeach;?></section>
<section class="card"><h2><?=$own?'Mis tareas':'Tareas de mis clases'?></h2>
<?php if(!$data['items']):?><p>No hay tareas con estos filtros. Comprueba tus clases o limpia la selección.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Clase</th><th>Tarea / proyecto</th><?php if(!$own):?><th>Estudiante</th><?php endif;?><th>Vencimiento</th><th>Estado / evaluación</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($data['items'] as $task):?><tr><td><?=e($task['subject'].' · '.$task['group_name'].' · '.$task['period'])?><small><?=e($task['career'])?></small></td><td><?=e($task['title'])?><p class="text-muted"><?=e($task['project_title'])?></p></td><?php if(!$own):?><td><?=e($task['student_name']??'Sin asignar')?></td><?php endif;?><td><?=e($task['due_date']??'Sin fecha')?></td><td><?=e(ProjectMetrics::LABELS[$task['status']])?><?php if($task['grade_result']!==null):?> · <?=(int)$task['grade_result']?>/<?=(int)$task['points']?><?php endif;?></td><td><a href="<?=e(base_url('projects/task.php?project_id='.$task['project_id'].'&task_id='.$task['id']))?>">Abrir tarea</a><?php if(!$own):?> · <a href="<?=e(base_url('projects/board.php?id='.$task['project_id']))?>">Evaluar en tablero</a><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
<nav aria-label="Páginas de tareas"><?php if($data['page']>1):?><a href="?<?=e(http_build_query(array_merge($filters,['page'=>$data['page']-1])))?>">Anterior</a><?php endif;?> <span>Página <?=$data['page']?> de <?=$data['pages']?></span> <?php if($data['page']<$data['pages']):?><a href="?<?=e(http_build_query(array_merge($filters,['page'=>$data['page']+1])))?>">Siguiente</a><?php endif;?></nav></section>
<?php require APP_PATH.'/app/views/partials/footer.php';?>
