<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';requireAuth();
$classes=Classroom::choices(current_user_id(),can('projects.manage.all'));
$id=(int)($_GET['id']??0);
if($id && !Classroom::canAccess($id,current_user_id(),can('projects.manage.all')))access_denied();
$class=$id?Classroom::find($id):null;
$page=max(1,(int)($_GET['page']??1));$participants=[];
if($class){$q=db()->prepare('SELECT u.first_name,u.last_name FROM classroom_members cm JOIN users u ON u.id=cm.student_id JOIN roles r ON r.id=u.role_id WHERE cm.classroom_id=? AND cm.is_active=1 AND u.is_active=1 AND r.code="estudiante" ORDER BY u.last_name,u.first_name,u.id LIMIT 26 OFFSET '.(($page-1)*25));$q->execute([$id]);$participants=$q->fetchAll();}
$pageTitle='Mis clases';require APP_PATH.'/app/views/partials/header.php';?>
<h1>Mis clases</h1><p>Solo aparecen las clases y personas con las que tienes una relación autorizada.</p>
<?php foreach($classes as $row):?><section class="card"><h2><?=e(Classroom::label($row))?></h2><p>Docente: <?=e($row['teacher_name'])?> · Semestre <?=(int)$row['semester_number']?></p><a href="<?=e(base_url('user/dashboard.php?classroom_id='.$row['id']))?>">Ver tareas</a> · <a href="<?=e(base_url('user/classes.php?id='.$row['id']))?>">Participantes</a></section><?php endforeach;?>
<?php if(!$classes):?><p>No tienes clases activas asignadas. Solicita al administrador que verifique la lista autorizada.</p><?php endif;?>
<?php if($class):?><section class="card"><h2>Participantes de <?=e(Classroom::label($class))?></h2><ul><?php foreach(array_slice($participants,0,25) as $person):?><li><?=e($person['first_name'].' '.$person['last_name'])?></li><?php endforeach;?></ul><nav><?php if($page>1):?><a href="?id=<?=$id?>&page=<?=$page-1?>">Anterior</a><?php endif;?> <?php if(count($participants)>25):?><a href="?id=<?=$id?>&page=<?=$page+1?>">Siguiente</a><?php endif;?></nav></section><?php endif;?>
<?php require APP_PATH.'/app/views/partials/footer.php';?>
