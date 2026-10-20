<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
requireAuth();
requirePermission('academic.view');
$me = current_user();
$role = $me['role_code'];
if (is_post() && isset($_POST['action']) && $_POST['action']==='update-course-status') {
    requireCsrf();
    $id=(int)post('id'); $status=post('status'); $grade=post('grade')!==''?(float)post('grade'):null;
    $ce=CourseEnrollment::find($id);
    // docente solo puede tocar sus assignments
    if($role==='docente'){
        $ta=TeachingAssignment::find((int)($ce['teaching_assignment_id'] ?? 0));
        if($ta===null || (int)$ta['teacher_id']!== (int)$me['id']){ flash_set('error','No autorizado.'); redirect('user/academic.php');}
    }
    try{ CourseEnrollment::updateStatus($id,$status,$grade); flash_set('success','Actualizado.');} catch(Throwable $e){ flash_set('error',$e->getMessage());}
    redirect('user/academic.php');
}
$pageTitle='Expediente Academico';
require APP_PATH . '/app/views/partials/header.php';
?>
<h1>Expediente Academico</h1>
<p class="text-muted"><?= e($me['first_name'].' '.$me['last_name']) ?> — <?= e($role) ?></p>

<?php if($role==='docente'): $ass=TeachingAssignment::byTeacher((int)$me['id']); ?>
  <h2>Mis asignaciones (Docente → Materia → Grupo)</h2>
  <?php if($ass===[]): ?><p class="text-muted">Sin asignaciones. Contacta al administrador.</p><?php endif; ?>
  <?php foreach($ass as $a): $stud=TeachingAssignment::students((int)$a['id']); ?>
    <section class="card">
      <h3><?= e($a['subject_code'].' - '.$a['subject_name']) ?> | Grupo <?= e($a['group_code']) ?> | <?= e($a['period_code']) ?> | <?= e($a['career_name']) ?> <span class="badge"><?= e($a['status']) ?></span></h3>
      <p class="text-muted">Estudiantes inscritos: <?= count($stud) ?></p>
      <table><thead><tr><th>Estudiante</th><th>Estado</th><th>Nota</th><th>Accion</th></tr></thead><tbody>
      <?php foreach($stud as $s): $ceRow=db()->prepare('SELECT id FROM course_enrollments WHERE enrollment_id=(SELECT id FROM enrollments WHERE student_id=? AND academic_period_id=? LIMIT 1) AND teaching_assignment_id=?'); $ceRow->execute([(int)$s['id'], (int)$a['academic_period_id'], (int)$a['id']]); $ceId=(int)$ceRow->fetchColumn(); ?>
        <tr><td><?= e($s['first_name'].' '.$s['last_name']) ?></td><td><?= e($s['course_status']) ?></td><td><?= e((string)($s['grade']??'')) ?></td><td><?php if($ceId): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update-course-status"><input type="hidden" name="id" value="<?= $ceId ?>"><select name="status"><option value="en_curso" <?= $s['course_status']==='en_curso'?'selected':'' ?>>en_curso</option><option value="aprobada" <?= $s['course_status']==='aprobada'?'selected':'' ?>>aprobada</option><option value="reprobada" <?= $s['course_status']==='reprobada'?'selected':'' ?>>reprobada</option></select><input type="number" name="grade" value="<?= e((string)($s['grade']??'')) ?>" min="0" max="100" step="0.01" style="width:80px"><button class="btn btn-sm btn-outline-dark">Guardar</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    </section>
  <?php endforeach; ?>

<?php elseif($role==='estudiante'): $enrolls=Enrollment::byStudent((int)$me['id']); $courses=CourseEnrollment::byStudent((int)$me['id']); ?>
  <h2>Mis matriculas</h2>
  <?php foreach($enrolls as $en): ?><div class="card"><strong><?= e($en['career_name']) ?> — Grupo <?= e($en['group_code']) ?> — Semestre <?= (int)$en['semester_number'] ?> — <?= e($en['period_code']) ?></strong> <span class="badge"><?= e($en['status']) ?></span></div><?php endforeach; ?>
  <h2>Mis materias (Inscripcion → Asignacion → Docente)</h2>
  <table><thead><tr><th>Materia</th><th>Cred</th><th>Grupo</th><th>Periodo</th><th>Docente</th><th>Estado</th><th>Nota</th></tr></thead><tbody>
  <?php foreach($courses as $c): ?><tr><td><?= e($c['subject_code'].' - '.$c['subject_name']) ?></td><td><?= e((string)$c['credits']) ?></td><td><?= e($c['group_code']) ?></td><td><?= e($c['period_code']) ?> (Sem <?= (int)$c['semester_number'] ?>)</td><td><?= e($c['teacher_name']) ?></td><td><span class="badge"><?= e($c['status']) ?></span></td><td><?= e((string)($c['grade']??'-')) ?></td></tr><?php endforeach; ?>
  </tbody></table>
  <?php if($courses===[]): ?><p class="text-muted">Sin inscripciones. El administrador te matriculara.</p><?php endif; ?>
<?php else: ?>
  <p>Panel academico disponible. Ve a <a href="<?= e(base_url('admin/academic.php')) ?>">Gestion academica</a>.</p>
<?php endif; ?>
<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
