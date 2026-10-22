<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
if (!can('projects.create') && !can('projects.manage.all')) {
    access_denied();
}

if (is_post()) {
    $action = post('action');
    $postedProjectId = (int) post('project_id');
    if ($action === 'create-project') ProjectController::create();
    if ($action === 'update-project' && $postedProjectId > 0) ProjectController::update($postedProjectId);
    if ($action === 'delete-project' && $postedProjectId > 0) ProjectController::delete($postedProjectId);
    flash_set('error', 'La accion solicitada no es valida.');
    redirect('projects/index.php');
}

$projectId = (int) ($_GET['id'] ?? 0);
$project = $projectId > 0 ? Project::find($projectId) : null;
if ($projectId > 0 && ($project === null || !Project::userCanManage($projectId, current_user_id(), can('projects.manage.all')))) {
    access_denied();
}

$classes=Classroom::choices(current_user_id(),can('projects.manage.all'));
if(!can('projects.manage.all'))$classes=array_values(array_filter($classes,fn($c)=>(int)$c['teacher_id']===current_user_id()));
$classId=(int)($project['classroom_id']??$_GET['classroom_id']??($classes[0]['id']??0));
$class=$classId?Classroom::find($classId):null;
if($classId && (!Classroom::canAccess($classId,current_user_id(),can('projects.manage.all')) || (!can('projects.manage.all') && (int)$class['teacher_id']!==current_user_id())))access_denied();
$students=$class?Classroom::students($classId):[];
$assignedIds = $project !== null ? Project::studentIds($projectId) : [];
$errors = form_errors();
$pageTitle = $project === null ? 'Crear proyecto' : 'Editar proyecto';
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head"><div><h1><?= e($pageTitle) ?></h1><p class="text-muted">Define los datos del proyecto. El docente responsable y los participantes se obtienen de la clase autorizada.</p></div></section>

<?php if ($errors !== []): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>

<section class="card">
  <form method="post" action="<?= e(base_url('projects/form.php' . ($projectId > 0 ? '?id=' . $projectId : ''))) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $project === null ? 'create-project' : 'update-project' ?>">
    <?php if ($project !== null): ?><input type="hidden" name="project_id" value="<?= $projectId ?>"><?php endif; ?>
    <div class="form-group"><label for="classroom_id">Clase</label>
    <?php if($project && $project['classroom_id']!==null): ?>
      <input type="hidden" name="classroom_id" value="<?=$classId?>"><p><?=e(Classroom::label($class))?></p>
    <?php else: ?>
      <select id="classroom_id" name="classroom_id" required data-project-class data-project-id="<?=$projectId?>"><option value="">Seleccionar clase</option>
      <?php foreach($classes as $choice):?><option value="<?=(int)$choice['id']?>" <?=(int)$choice['id']===$classId?'selected':''?>><?=e(Classroom::label($choice))?></option><?php endforeach;?>
      </select><script src="<?=e(asset('js/project-class.js'))?>" defer></script>
    <?php endif; ?>
    <span class="error-msg"><?=e($errors['classroom_id']??'')?></span></div>
    <div class="form-group"><label>Docente responsable</label><input type="text" value="<?=e($class['teacher_name']??'Selecciona una clase')?>" disabled></div>
    <?php if(!$classes):?><p>No tienes clases autorizadas. Solicita al administrador que registre tu clase y sus participantes.</p><?php endif;?>
    <div class="form-group"><label for="title">Titulo del proyecto</label><input type="text" id="title" name="title" maxlength="150" required value="<?= e($project['title'] ?? '') ?>"><span class="error-msg"><?= e($errors['title'] ?? '') ?></span></div>
    <div class="form-group"><label for="description">Descripcion general</label><textarea id="description" name="description" maxlength="5000" required><?= e($project['description'] ?? '') ?></textarea><span class="error-msg"><?= e($errors['description'] ?? '') ?></span></div>
    <div class="form-group"><label for="objectives">Objetivos del proyecto</label><textarea id="objectives" name="objectives" maxlength="5000" required><?= e($project['objectives'] ?? '') ?></textarea><span class="error-msg"><?= e($errors['objectives'] ?? '') ?></span></div>
    <div class="form-row">
      <div class="form-group"><label for="start_date">Fecha de inicio</label><input type="date" id="start_date" name="start_date" required value="<?= e($project['start_date'] ?? '') ?>"><span class="error-msg"><?= e($errors['start_date'] ?? '') ?></span></div>
      <div class="form-group"><label for="end_date">Fecha limite</label><input type="date" id="end_date" name="end_date" required value="<?= e($project['end_date'] ?? '') ?>"><span class="error-msg"><?= e($errors['end_date'] ?? '') ?></span></div>
    </div>
    <fieldset class="permission-fieldset"><legend>Estudiantes asignados</legend><div class="student-selector">
      <?php foreach ($students as $student): ?><label class="permission-option"><input type="checkbox" name="student_ids[]" value="<?= (int) $student['id'] ?>" <?= in_array((int) $student['id'], $assignedIds, true) ? 'checked' : '' ?>><span><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong><small>Estudiante</small></span></label><?php endforeach; ?>
    </div><span class="error-msg"><?= e($errors['student_ids'] ?? '') ?></span></fieldset>
    <div class="form-actions"><button type="submit" class="btn btn-primary" onclick="return confirm('<?= $project === null ? '¿Estas seguro de crear este proyecto?' : '¿Estas seguro de guardar los cambios del proyecto?' ?>')"><?= $project === null ? 'Crear proyecto' : 'Guardar cambios' ?></button><a class="btn btn-outline-dark" href="<?= e(base_url('projects/index.php')) ?>">Cancelar</a></div>
  </form>
  <?php if ($project !== null): ?><form method="post" action="<?= e(base_url('projects/form.php?id=' . $projectId)) ?>" class="delete-project-form"><input type="hidden" name="action" value="delete-project"><?= csrf_field() ?><input type="hidden" name="project_id" value="<?= $projectId ?>"><button class="btn btn-outline-dark" type="submit" onclick="return confirm('¿Estas seguro de eliminar este proyecto? Esta accion no se puede deshacer.')">Eliminar proyecto</button></form><?php endif; ?>
</section>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
