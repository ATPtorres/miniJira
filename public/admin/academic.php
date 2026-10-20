<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
requireAuth();
requirePermission('academic.view');
$tab = $_GET['tab'] ?? 'periods';
$allowed=['periods','careers','subjects','plans','groups','assignments','enrollments'];
if(!in_array($tab,$allowed,true)) $tab='periods';

if(is_post()){
    $action=post('action');
    try {
        match($action){
            'create-period' => AcademicController::createPeriod(),
            'update-period' => AcademicController::updatePeriod(),
            'create-career' => AcademicController::createCareer(),
            'update-career' => AcademicController::updateCareer(),
            'create-subject' => AcademicController::createSubject(),
            'update-subject' => AcademicController::updateSubject(),
            'create-plan' => AcademicController::createPlan(),
            'update-plan' => AcademicController::updatePlan(),
            'sync-plan-subjects' => AcademicController::syncPlanSubjects(),
            'create-group' => AcademicController::createGroup(),
            'update-group' => AcademicController::updateGroup(),
            'create-assignment' => AcademicController::createAssignment(),
            'update-assignment' => AcademicController::updateAssignment(),
            'delete-assignment' => AcademicController::deleteAssignment(),
            'create-enrollment' => AcademicController::createEnrollment(),
            'update-enrollment' => AcademicController::updateEnrollment(),
            'enroll-course' => AcademicController::enrollCourse(),
            'update-course-status' => AcademicController::updateCourseStatus(),
            default => null
        };
    } catch(Throwable $e){ flash_set('error',$e->getMessage()); redirect('admin/academic.php?tab='.$tab); }
    if(!headers_sent()){ flash_set('error','Accion no valida.'); redirect('admin/academic.php?tab='.$tab); }
}

$periods=AcademicPeriod::all();
$careers=Career::all();
$subjects=Subject::all();
$plans=StudyPlan::all();
$groups=AcademicGroup::all();
$assignments=TeachingAssignment::all();
$enrollments=Enrollment::all();
$teachers=array_filter(User::all(), fn($u)=>$u['role_code']==='docente' && (int)$u['is_active']===1);
$students=array_filter(User::all(), fn($u)=>$u['role_code']==='estudiante' && (int)$u['is_active']===1);
$planDetail=null;
if($tab==='plans' && isset($_GET['plan'])){ $planDetail=StudyPlan::find((int)$_GET['plan']); }
$enrollDetail=null;
if($tab==='enrollments' && isset($_GET['enrollment'])){ $enrollDetail=Enrollment::find((int)$_GET['enrollment']); }

$pageTitle='Gestion Academica';
require APP_PATH . '/app/views/partials/header.php';
?>
<section class="dash-hero">
  <div class="dash-hero-body"><h1>Gestion Academica <span class="role-badge">Administrador</span></h1><p class="text-muted">Carreras → Planes → Materias → Periodos → Grupos → Asignaciones → Matriculas</p></div>
  <div class="dash-hero-actions"><a class="btn btn-light" href="<?= e(base_url('admin/dashboard.php')) ?>">Volver al panel</a></div>
</section>

<style>.tabs{display:flex;gap:6px;flex-wrap:wrap;margin:16px 0}.tabs a{padding:8px 12px;border-radius:8px;background:var(--gray-100);text-decoration:none;color:var(--navy);font-weight:600;font-size:.85rem}.tabs a.active{background:var(--navy);color:#fff}.card{padding:16px;margin-bottom:18px}.form-row{display:flex;gap:12px;flex-wrap:wrap}.form-row>div{flex:1 1 200px}</style>
<nav class="tabs">
  <?php foreach($allowed as $t): ?><a class="<?= $t===$tab?'active':'' ?>" href="<?= e(base_url('admin/academic.php?tab='.$t)) ?>"><?= e(ucfirst($t)) ?></a><?php endforeach; ?>
</nav>

<?= flash_display() ?>

<?php if($tab==='periods'): ?>
<section class="card">
  <h2>Crear periodo academico</h2>
  <form method="post" action="<?= e(base_url('admin/academic.php?tab=periods')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="create-period">
    <div class="form-row"><div class="form-group"><label>Codigo (2026-1)</label><input name="code" required maxlength="20"></div><div class="form-group"><label>Nombre</label><input name="name" required></div></div>
    <div class="form-row"><div class="form-group"><label>Inicio</label><input type="date" name="start_date" required></div><div class="form-group"><label>Fin</label><input type="date" name="end_date" required></div></div>
    <button class="btn btn-primary">Crear periodo</button>
  </form>
</section>
<section class="table-wrap"><table><thead><tr><th>Codigo</th><th>Nombre</th><th>Inicio</th><th>Fin</th><th>Activo</th><th>Accion</th></tr></thead><tbody>
<?php foreach($periods as $p): ?><tr><td><?= e($p['code']) ?></td><td><?= e($p['name']) ?></td><td><?= e($p['start_date']) ?></td><td><?= e($p['end_date']) ?></td><td><?= (int)$p['is_active']?'Si':'No' ?></td><td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update-period"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="code" value="<?= e($p['code']) ?>"><input type="hidden" name="name" value="<?= e($p['name']) ?>"><input type="hidden" name="start_date" value="<?= e($p['start_date']) ?>"><input type="hidden" name="end_date" value="<?= e($p['end_date']) ?>"><input type="hidden" name="is_active" value="<?= (int)$p['is_active']?0:1 ?>"><button class="btn btn-sm btn-outline-dark"><?= (int)$p['is_active']?'Desactivar':'Activar' ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></section>

<?php elseif($tab==='careers'): ?>
<section class="card">
  <h2>Crear carrera</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create-career">
    <div class="form-row"><div class="form-group"><label>Codigo (IS)</label><input name="code" required></div><div class="form-group"><label>Nombre</label><input name="name" required></div><div class="form-group"><label>Semestres</label><input type="number" name="duration_semesters" value="8" min="1" max="12" required></div></div>
    <div class="form-group"><label>Descripcion</label><textarea name="description" rows="2"></textarea></div>
    <button class="btn btn-primary">Crear carrera</button>
  </form>
</section>
<section class="table-wrap"><table><thead><tr><th>Codigo</th><th>Nombre</th><th>Duracion</th><th>Activa</th><th>Materias en malla</th></tr></thead><tbody>
<?php foreach($careers as $c): ?><tr><td><?= e($c['code']) ?></td><td><?= e($c['name']) ?></td><td><?= (int)$c['duration_semesters'] ?></td><td><?= (int)$c['is_active']?'Si':'No' ?></td><td><?= Career::countSubjects((int)$c['id']) ?></td></tr><?php endforeach; ?>
</tbody></table></section>

<?php elseif($tab==='subjects'): ?>
<section class="card">
  <h2>Crear materia (catalogo global)</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create-subject">
    <div class="form-row"><div class="form-group"><label>Codigo (MAT101)</label><input name="code" required></div><div class="form-group"><label>Nombre</label><input name="name" required></div><div class="form-group"><label>Creditos</label><input type="number" name="credits" min="0" max="10"></div></div>
    <div class="form-group"><label>Descripcion</label><textarea name="description" rows="2"></textarea></div>
    <button class="btn btn-primary">Crear materia</button>
  </form>
</section>
<section class="table-wrap"><table><thead><tr><th>Codigo</th><th>Nombre</th><th>Creditos</th><th>Activa</th><th>Prereqs</th></tr></thead><tbody>
<?php foreach($subjects as $s): $pre=Subject::prerequisites((int)$s['id']); ?><tr><td><?= e($s['code']) ?></td><td><?= e($s['name']) ?></td><td><?= e((string)($s['credits']??'')) ?></td><td><?= (int)$s['is_active']?'Si':'No' ?></td><td><?= e(implode(', ', array_map(fn($p)=>$p['code'],$pre))) ?></td></tr><?php endforeach; ?>
</tbody></table></section>

<?php elseif($tab==='plans'): ?>
<section class="card">
  <h2>Crear plan de estudios</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create-plan">
    <div class="form-row"><div class="form-group"><label>Carrera</label><select name="career_id" required><option value="">--</option><?php foreach($careers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Codigo (IS-2024)</label><input name="code" required></div><div class="form-group"><label>Nombre</label><input name="name" required></div></div>
    <div class="form-row"><div class="form-group"><label>Version</label><input name="version" value="2024"></div><div class="form-group"><label>Valido desde</label><input type="date" name="valid_from"></div><div class="form-group"><label><input type="checkbox" name="is_current" value="1"> Vigente</label></div></div>
    <button class="btn btn-primary">Crear plan</button>
  </form>
</section>
<section class="table-wrap"><table><thead><tr><th>Carrera</th><th>Codigo</th><th>Nombre</th><th>Vigente</th><th>Malla</th></tr></thead><tbody>
<?php foreach($plans as $pl): ?><tr><td><?= e($pl['career_name']) ?></td><td><?= e($pl['code']) ?></td><td><?= e($pl['name']) ?></td><td><?= (int)$pl['is_current']?'Si':'No' ?></td><td><a class="btn btn-sm btn-outline-dark" href="<?= e(base_url('admin/academic.php?tab=plans&plan='.(int)$pl['id'])) ?>">Ver malla</a></td></tr><?php endforeach; ?>
</tbody></table></section>
<?php if($planDetail): $malla=StudyPlan::subjects((int)$planDetail['id']); ?>
<section class="card">
  <h2>Malla: <?= e($planDetail['name']) ?> (<?= e($planDetail['code']) ?>)</h2>
  <p class="text-muted">Distribucion por semestre. Agrega materias desde catalogo.</p>
  <?php foreach(array_unique(array_map(fn($r)=>(int)$r['semester_number'],$malla)) as $sem): ?>
    <h4>Semestre <?= $sem ?></h4><ul><?php foreach(array_filter($malla, fn($r)=>(int)$r['semester_number']===$sem) as $mm): ?><li><?= e($mm['subject_code']) ?> - <?= e($mm['subject_name']) ?> (<?= e((string)$mm['credits']) ?> cr)</li><?php endforeach; ?></ul>
  <?php endforeach; ?>
  <h3>Editar malla (reemplaza completa)</h3>
  <form method="post" id="mallaForm"><?= csrf_field() ?><input type="hidden" name="action" value="sync-plan-subjects"><input type="hidden" name="plan_id" value="<?= (int)$planDetail['id'] ?>">
    <div id="mallaRows"><?php foreach($malla as $mm): ?><div class="form-row" style="align-items:end"><div class="form-group"><label>Materia</label><select name="subject_id[]"><?php foreach($subjects as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===(int)$mm['subject_id']?'selected':'' ?>><?= e($s['code'].' - '.$s['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Semestre</label><input type="number" name="semester_number[]" value="<?= (int)$mm['semester_number'] ?>" min="1" max="12"></div><div><button type="button" onclick="this.closest('.form-row').remove()" class="btn btn-sm btn-outline-dark">X</button></div></div><?php endforeach; ?></div>
    <button type="button" class="btn btn-sm btn-outline-dark" onclick="addMallaRow()">+ Agregar materia</button>
    <button class="btn btn-primary">Guardar malla</button>
  </form>
  <script>
  function addMallaRow(){
    const tpl=document.createElement('div'); tpl.className='form-row'; tpl.style.alignItems='end';
    tpl.innerHTML='<div class="form-group"><label>Materia</label><select name="subject_id[]"><?php foreach($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['code'].' - '.$s['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Semestre</label><input type="number" name="semester_number[]" value="1" min="1" max="12"></div><div><button type="button" onclick="this.closest(\'.form-row\').remove()" class="btn btn-sm btn-outline-dark">X</button></div>';
    document.getElementById('mallaRows').appendChild(tpl);
  }
  </script>
</section>
<?php endif; ?>

<?php elseif($tab==='groups'): ?>
<section class="card">
  <h2>Crear grupo</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create-group">
    <div class="form-row"><div class="form-group"><label>Codigo (A/B)</label><input name="code" required></div><div class="form-group"><label>Nombre (Grupo A - 3er Sem)</label><input name="name" required></div><div class="form-group"><label>Capacidad</label><input type="number" name="capacity" value="30" min="1"></div></div>
    <div class="form-row"><div class="form-group"><label>Carrera</label><select name="career_id" required><option value="">--</option><?php foreach($careers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Plan</label><select name="study_plan_id"><option value="">-- sin plan --</option><?php foreach($plans as $pl): ?><option value="<?= (int)$pl['id'] ?>"><?= e($pl['code']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Semestre</label><input type="number" name="semester_number" value="1" min="1" max="12" required></div></div>
    <div class="form-group"><label>Periodo</label><select name="academic_period_id" required><option value="">--</option><?php foreach($periods as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['code'].' - '.$p['name']) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary">Crear grupo</button>
  </form>
</section>
<section class="table-wrap"><table><thead><tr><th>Grupo</th><th>Carrera</th><th>Sem</th><th>Periodo</th><th>Cap.</th><th>Ocup.</th></tr></thead><tbody>
<?php foreach($groups as $g): ?><tr><td><?= e($g['code'].' - '.$g['name']) ?></td><td><?= e($g['career_name']) ?></td><td><?= (int)$g['semester_number'] ?></td><td><?= e($g['period_code']) ?></td><td><?= (int)$g['capacity'] ?></td><td><?= AcademicGroup::countEnrollments((int)$g['id']) ?></td></tr><?php endforeach; ?>
</tbody></table></section>

<?php elseif($tab==='assignments'): ?>
<section class="card">
  <h2>Crear asignacion academica (Docente + Materia + Grupo + Periodo)</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create-assignment">
    <div class="form-row"><div class="form-group"><label>Docente</label><select name="teacher_id" required><option value="">--</option><?php foreach($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Materia</label><select name="subject_id" required><option value="">--</option><?php foreach($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['code'].' - '.$s['name']) ?></option><?php endforeach; ?></select></div></div>
    <div class="form-row"><div class="form-group"><label>Grupo</label><select name="group_id" required><option value="">--</option><?php foreach($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['career_name'].' - '.$g['name'].' ('.$g['period_code'].')') ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Carrera</label><select name="career_id" required><option value="">--</option><?php foreach($careers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Periodo</label><select name="academic_period_id" required><option value="">--</option><?php foreach($periods as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['code']) ?></option><?php endforeach; ?></select></div></div>
    <button class="btn btn-primary">Crear asignacion</button>
  </form>
  <p class="text-muted" style="margin-top:8px">Regla: 1 materia + 1 grupo + 1 periodo = 1 docente. No duplicar.</p>
</section>
<section class="table-wrap"><table><thead><tr><th>Docente</th><th>Materia</th><th>Grupo</th><th>Carrera</th><th>Periodo</th><th>Estado</th><th>Estudiantes</th><th>Accion</th></tr></thead><tbody>
<?php foreach($assignments as $a): ?><tr><td><?= e($a['teacher_name']) ?></td><td><?= e($a['subject_code'].' - '.$a['subject_name']) ?></td><td><?= e($a['group_code']) ?></td><td><?= e($a['career_name']) ?></td><td><?= e($a['period_code']) ?></td><td><?= e($a['status']) ?></td><td><?= count(TeachingAssignment::students((int)$a['id'])) ?></td><td><form method="post" class="inline-form" onsubmit="return confirm('¿Eliminar? Si tiene cursadas no se podra.');"><?= csrf_field() ?><input type="hidden" name="action" value="delete-assignment"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-outline-dark">Eliminar</button></form></td></tr><?php endforeach; ?>
</tbody></table></section>

<?php elseif($tab==='enrollments'): ?>
<section class="card">
  <h2>Matricular estudiante (Enrollment)</h2>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create-enrollment">
    <div class="form-row"><div class="form-group"><label>Estudiante</label><select name="student_id" required><option value="">--</option><?php foreach($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['first_name'].' '.$s['last_name'].' ('.$s['email'].')') ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Carrera</label><select name="career_id" required><option value="">--</option><?php foreach($careers as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Plan</label><select name="study_plan_id"><option value="">--</option><?php foreach($plans as $pl): ?><option value="<?= (int)$pl['id'] ?>"><?= e($pl['code']) ?></option><?php endforeach; ?></select></div></div>
    <div class="form-row"><div class="form-group"><label>Grupo</label><select name="group_id" required><option value="">--</option><?php foreach($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['career_name'].' - '.$g['name'].' ('.$g['period_code'].') Cap:'.$g['capacity']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Periodo</label><select name="academic_period_id" required><option value="">--</option><?php foreach($periods as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['code']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Semestre</label><input type="number" name="semester_number" value="1" min="1" max="12" required></div></div>
    <button class="btn btn-primary">Matricular</button>
  </form>
</section>
<section class="table-wrap"><table><thead><tr><th>Estudiante</th><th>Carrera</th><th>Grupo</th><th>Periodo</th><th>Sem</th><th>Estado</th><th>Cursadas</th><th>Accion</th></tr></thead><tbody>
<?php foreach($enrollments as $e): $ce=Enrollment::courseEnrollments((int)$e['id']); ?><tr><td><?= e($e['student_name']) ?></td><td><?= e($e['career_name']) ?></td><td><?= e($e['group_code']) ?></td><td><?= e($e['period_code']) ?></td><td><?= (int)$e['semester_number'] ?></td><td><?= e($e['status']) ?></td><td><?= count($ce) ?></td><td><a class="btn btn-sm btn-outline-dark" href="<?= e(base_url('admin/academic.php?tab=enrollments&enrollment='.(int)$e['id'])) ?>">Inscribir materias</a></td></tr><?php endforeach; ?>
</tbody></table></section>
<?php if($enrollDetail): $ces=Enrollment::courseEnrollments((int)$enrollDetail['id']); $available=array_filter($assignments, fn($a)=>(int)$a['group_id']===(int)$enrollDetail['group_id'] && (int)$a['academic_period_id']===(int)$enrollDetail['academic_period_id']); ?>
<section class="card">
  <h2>Cursadas de <?= e($enrollDetail['student_name']) ?> — <?= e($enrollDetail['career_name']) ?> Grupo <?= e($enrollDetail['group_code']) ?> (<?= e($enrollDetail['period_code']) ?>)</h2>
  <form method="post" style="margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="enroll-course"><input type="hidden" name="enrollment_id" value="<?= (int)$enrollDetail['id'] ?>">
    <div class="form-row"><div class="form-group"><label>Asignacion (materia del grupo en ese periodo)</label><select name="teaching_assignment_id" required><option value="">--</option><?php foreach($available as $av): ?><option value="<?= (int)$av['id'] ?>"><?= e($av['subject_code'].' - '.$av['subject_name'].' | Doc: '.$av['teacher_name']) ?></option><?php endforeach; ?></select></div><div style="align-self:end"><button class="btn btn-primary">Inscribir materia</button></div></div>
  </form>
  <table><thead><tr><th>Materia</th><th>Docente</th><th>Estado</th><th>Nota</th><th>Accion</th></tr></thead><tbody>
  <?php foreach($ces as $ce): ?><tr><td><?= e($ce['subject_code'].' - '.$ce['subject_name']) ?></td><td><?= e($ce['teacher_name']) ?></td><td><?= e($ce['status']) ?></td><td><?= e((string)($ce['grade']??'')) ?></td><td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update-course-status"><input type="hidden" name="id" value="<?= (int)$ce['id'] ?>"><input type="hidden" name="ref" value="<?= e(base_url('admin/academic.php?tab=enrollments&enrollment='.(int)$enrollDetail['id'])) ?>"><select name="status"><option value="inscrita" <?= $ce['status']==='inscrita'?'selected':'' ?>>inscrita</option><option value="en_curso" <?= $ce['status']==='en_curso'?'selected':'' ?>>en_curso</option><option value="aprobada" <?= $ce['status']==='aprobada'?'selected':'' ?>>aprobada</option><option value="reprobada" <?= $ce['status']==='reprobada'?'selected':'' ?>>reprobada</option><option value="retirada" <?= $ce['status']==='retirada'?'selected':'' ?>>retirada</option></select><input type="number" name="grade" value="<?= e((string)($ce['grade']??'')) ?>" min="0" max="100" step="0.01" placeholder="Nota" style="width:80px"><button class="btn btn-sm btn-outline-dark">Guardar</button></form></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
<?php endif; ?>
<?php endif; ?>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
