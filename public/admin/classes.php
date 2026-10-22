<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';requireAuth();requirePermission('classes.manage');
if(is_post())ClassroomController::save();
$id=(int)($_GET['id']??0);$class=$id?Classroom::find($id):null;
if($id && !$class)access_denied();
$page=max(1,(int)($_GET['page']??1));
$q=db()->prepare('SELECT c.*,CONCAT(u.first_name," ",u.last_name) AS teacher_name,(SELECT COUNT(*) FROM classroom_members cm WHERE cm.classroom_id=c.id AND cm.is_active=1) AS participants FROM classrooms c JOIN users u ON u.id=c.teacher_id ORDER BY c.id DESC LIMIT 26 OFFSET '.(($page-1)*25));$q->execute();$classes=$q->fetchAll();$hasNext=count($classes)>25;$classes=array_slice($classes,0,25);
$teachers=User::activeByRole('docente');$roster='';
if($class){$q=db()->prepare('SELECT u.email,u.email_encrypted FROM classroom_members cm JOIN users u ON u.id=cm.student_id WHERE cm.classroom_id=? AND cm.is_active=1 ORDER BY u.id');$q->execute([$id]);$values=[];foreach($q as $u)$values[]=!empty($u['email_encrypted'])?EncryptionService::decrypt($u['email_encrypted']):(string)$u['email'];$roster=implode("\n",$values);}
$pageTitle='Clases y participantes';require APP_PATH.'/app/views/partials/header.php';
?>
<section class="panel-head"><div><h1>Clases y participantes</h1><p>Registra la lista autorizada de cada clase según la información de la universidad.</p></div><a href="<?=e(base_url('admin/classes.php'))?>" class="btn btn-outline-dark">Nueva clase</a></section>
<section class="card"><h2><?= $class?'Editar clase':'Registrar clase' ?></h2>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?><input type="hidden" name="id" value="<?=$id?>">
<div class="form-row">
<?php foreach(['external_code'=>['Código externo',100],'subject'=>['Materia',150],'career'=>['Carrera',150],'group_name'=>['Grupo',80],'period'=>['Período',80]] as $key=>[$label,$max]): ?>
<div class="form-group"><label for="<?=e($key)?>"><?=e($label)?></label><input id="<?=e($key)?>" name="<?=e($key)?>" maxlength="<?=$max?>" required value="<?=e((string)($class[$key]??''))?>"></div>
<?php endforeach; ?>
<div class="form-group"><label for="semester_number">Semestre curricular</label><input id="semester_number" name="semester_number" type="number" min="1" max="12" required value="<?=(int)($class['semester_number']??1)?>"></div>
<div class="form-group"><label for="teacher_id">Docente autorizado</label><select name="teacher_id" id="teacher_id" required><option value="">Seleccionar</option><?php foreach($teachers as $teacher): ?><option value="<?=(int)$teacher['id']?>" <?= (int)$teacher['id']===(int)($class['teacher_id']??0)?'selected':'' ?>><?=e($teacher['first_name'].' '.$teacher['last_name'])?></option><?php endforeach;?></select></div>
</div>
<label><input type="checkbox" name="is_active" value="1" <?=!$class || $class['is_active']?'checked':''?>> Clase activa</label>
<div class="form-group"><label for="roster">Lista completa de estudiantes autorizados: un correo por línea</label><textarea id="roster" name="roster" rows="6"><?=e($roster)?></textarea></div>
<div class="form-group"><label for="roster_file">O importar CSV con el correo en la primera columna (máximo 2 MB)</label><input type="file" id="roster_file" name="roster_file" accept=".csv,text/csv,text/plain"></div>
<p>Guardar reemplaza la lista autorizada de esta clase. Los correos omitidos pierden el acceso; una lista vacía retira a todos. El CSV reemplaza el cuadro de texto. Las cuentas deben existir previamente.</p>
<button class="btn btn-primary">Guardar clase y participantes</button>
</form></section>
<section class="card"><h2>Clases registradas</h2><div class="table-wrap"><table><thead><tr><th>Clase</th><th>Docente</th><th>Participantes</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
<?php foreach($classes as $row):?><tr><td><?=e(Classroom::label($row))?></td><td><?=e($row['teacher_name'])?></td><td><?=(int)$row['participants']?></td><td><?=$row['is_active']?'Activa':'Inactiva'?></td><td><a href="<?=e(base_url('admin/classes.php?id='.$row['id']))?>">Editar</a></td></tr><?php endforeach; ?>
</tbody></table></div><nav><?php if($page>1):?><a href="?page=<?=$page-1?>">Anterior</a><?php endif;?> <span>Página <?=$page?></span> <?php if($hasNext):?><a href="?page=<?=$page+1?>">Siguiente</a><?php endif;?></nav></section>
<?php if($class):$q=db()->prepare('SELECT a.action,a.created_at,CONCAT(u.first_name," ",u.last_name) AS actor_name FROM classroom_audit a LEFT JOIN users u ON u.id=a.actor_id WHERE a.classroom_id=? ORDER BY a.id DESC LIMIT 10');$q->execute([$id]);?>
<section class="card"><h2>Últimos cambios de acceso</h2><?php foreach($q as $entry):?><p><?=e($entry['created_at'].' · '.$entry['action'].' · '.($entry['actor_name']??'Sistema'))?></p><?php endforeach;?></section>
<?php endif;?>
<?php require APP_PATH.'/app/views/partials/footer.php'; ?>
