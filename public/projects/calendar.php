<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

requireAuth();
$projectId = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
if ($projectId <= 0 || !Project::userCanAccess($projectId, current_user_id(), can('projects.manage.all'))) {
    access_denied();
}
if (is_post()) {
    $action = post('action');
    if ($action === 'create-event') ProjectController::createEvent($projectId);
    if ($action === 'update-event') ProjectController::updateEvent($projectId);
    if ($action === 'delete-event') ProjectController::deleteEvent($projectId);
    flash_set('error', 'La accion solicitada no es valida.');
    redirect('projects/calendar.php?id=' . $projectId);
}

$project = Project::find($projectId);
$canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));

// Mes a mostrar
$monthParam = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$year = (int) substr($monthParam, 0, 4);
$month = (int) substr($monthParam, 5, 2);
$firstDay = new DateTime("$year-$month-01");
$startDayOfWeek = (int) $firstDay->format('w'); // 0=dom, 6=sab
$daysInMonth = (int) $firstDay->format('t');
$prevMonth = (clone $firstDay)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $firstDay)->modify('+1 month')->format('Y-m');
$today = date('Y-m-d');

$monthNames = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$monthLabel = ($monthNames[$month] ?? '') . ' de ' . $year;

// Fecha por defecto del boton "Nuevo evento" (hoy si esta dentro del proyecto)
$defaultEventDate = ($today >= $project['start_date'] && $today <= $project['end_date']) ? $today : '';

/**
 * Atributos data-* de un evento: alimentan el modal (crear/editar/ver).
 */
function calendar_event_attrs(array $evt): string
{
    $desc = str_replace(["\r\n", "\r", "\n"], '&#10;', e($evt['description'] ?? ''));
    $type = (string) $evt['event_type'];
    return ' data-event-id="' . (int) $evt['id'] . '"'
        . ' data-title="' . e($evt['title']) . '"'
        . ' data-date="' . e($evt['event_date']) . '"'
        . ' data-time="' . e((string) ($evt['event_time'] ?? '')) . '"'
        . ' data-type="' . e($type) . '"'
        . ' data-type-label="' . e(ProjectEvent::TYPES[$type] ?? $type) . '"'
        . ' data-creator="' . e($evt['creator_name']) . '"'
        . ' data-desc="' . $desc . '"';
}

// Eventos del mes
$events = ProjectEvent::forProject($projectId, $monthParam);
$eventsByDate = [];
foreach ($events as $evt) {
    $eventsByDate[$evt['event_date']][] = $evt;
}

// Tareas con due_date en este mes
$tasks = Task::forProject($projectId);
$tasksByDate = [];
foreach ($tasks as $task) {
    if ($task['due_date'] !== null && substr($task['due_date'], 0, 7) === $monthParam) {
        $tasksByDate[$task['due_date']][] = $task;
    }
}

$errors = form_errors();
$pageTitle = 'Calendario · ' . $project['title'];
?>
<?php require APP_PATH . '/app/views/partials/header.php'; ?>

<section class="panel-head">
  <div>
    <h1><?= e($project['title']) ?></h1>
    <p class="text-muted">Responsable: <?= e($project['teacher_name']) ?> · <?= e(date('d/m/Y', strtotime($project['start_date']))) ?> — <?= e(date('d/m/Y', strtotime($project['end_date']))) ?></p>
  </div>
  <div class="form-actions">
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/index.php')) ?>">Mis proyectos</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/board.php?id=' . $projectId)) ?>">Tablero</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/dashboard.php?id=' . $projectId)) ?>">Panel</a>
    <a class="btn btn-outline-dark" href="<?= e(base_url('projects/files.php?id=' . $projectId)) ?>">Archivos</a>
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?= e(base_url('projects/form.php?id=' . $projectId)) ?>">Editar proyecto</a>
    <?php endif; ?>
  </div>
</section>

<div class="calendar-nav">
  <a class="btn btn-outline-dark btn-sm" href="<?= e(base_url('projects/calendar.php?id=' . $projectId . '&month=' . $prevMonth)) ?>">&laquo; Anterior</a>
  <h2><?= e(ucfirst($monthLabel)) ?></h2>
  <a class="btn btn-outline-dark btn-sm" href="<?= e(base_url('projects/calendar.php?id=' . $projectId . '&month=' . $nextMonth)) ?>">Siguiente &raquo;</a>
  <a class="btn btn-outline-dark btn-sm" href="<?= e(base_url('projects/calendar.php?id=' . $projectId)) ?>">Hoy</a>
  <?php if ($canManage): ?>
    <button type="button" class="btn btn-primary btn-sm btn-new-event" aria-haspopup="dialog" aria-controls="event-modal" data-default-date="<?= e($defaultEventDate) ?>">+ Nuevo evento</button>
  <?php endif; ?>
</div>

<section class="calendar-legend">
  <span class="legend-item"><span class="dot dot-task"></span> Tarea</span>
  <span class="legend-item"><span class="dot dot-milestone"></span> Hito</span>
  <span class="legend-item"><span class="dot dot-delivery"></span> Entrega</span>
  <span class="legend-item"><span class="dot dot-custom"></span> Personalizado</span>
  <span class="legend-item"><span class="dot dot-project-start"></span> Inicio proyecto</span>
  <span class="legend-item"><span class="dot dot-project-end"></span> Fin proyecto</span>
  <?php if ($canManage): ?><span class="legend-item legend-hint">Selecciona el día para crear un evento</span><?php endif; ?>
</section>

<?php if ($errors !== []): ?>
<div class="alert alert-error"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<section class="calendar-grid" aria-label="Calendario mensual">
  <div class="calendar-header"><span>Lun</span><span>Mar</span><span>Mie</span><span>Jue</span><span>Vie</span><span>Sab</span><span>Dom</span></div>
  <div class="calendar-body">
  <?php
  $day = 1;
  for ($row = 0; $row < 6; $row++) {
      for ($col = 0; $col < 7; $col++) {
          if ($row === 0 && $col < ($startDayOfWeek === 0 ? 6 : $startDayOfWeek - 1)) {
              echo '<div class="calendar-day other-month"></div>';
              continue;
          }
          if ($day > $daysInMonth) {
              echo '<div class="calendar-day other-month"></div>';
              continue;
          }
          $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
          $isToday = $dateStr === $today;
          $isProjectStart = $dateStr === $project['start_date'];
          $isProjectEnd = $dateStr === $project['end_date'];
          $dayEvents = $eventsByDate[$dateStr] ?? [];
          $dayTasks = $tasksByDate[$dateStr] ?? [];
          $inRange = $dateStr >= $project['start_date'] && $dateStr <= $project['end_date'];
          $classes = ['calendar-day'];
          if ($isToday) $classes[] = 'today';
          if ($isProjectStart) $classes[] = 'project-start';
          if ($isProjectEnd) $classes[] = 'project-end';
          if ($dayTasks !== [] || $dayEvents !== []) $classes[] = 'has-events';
          if (!$inRange) $classes[] = 'outside-project';
          if ($canManage && $inRange) $classes[] = 'day-clickable';
          ?>
          <div class="<?= implode(' ', $classes) ?>" data-date="<?= $dateStr ?>">
            <?php if ($canManage && $inRange): ?>
              <button type="button" class="day-number calendar-create" aria-label="Crear evento el <?= e(date('d/m/Y', strtotime($dateStr))) ?>" aria-haspopup="dialog" aria-controls="event-modal"><?= $day ?></button>
            <?php else: ?><div class="day-number"><?= $day ?></div><?php endif; ?>
            <div class="day-events">
              <?php if ($isProjectStart): ?><span class="event-dot dot-project-start" title="Inicio del proyecto"></span><?php endif; ?>
              <?php if ($isProjectEnd): ?><span class="event-dot dot-project-end" title="Fin del proyecto"></span><?php endif; ?>
              <?php foreach ($dayTasks as $task): ?>
                <a href="<?= e(base_url('projects/task.php?project_id='.$projectId.'&task_id='.$task['id'])) ?>" aria-label="Abrir tarea: <?= e($task['title']) ?>" class="event-dot dot-task task-due" data-due="<?= e($task['due_date'] . 'T23:59:59') ?>" title="Tarea: <?= e($task['title']) ?> — <?= e($task['priority'] ?? 'medium') ?>"><?= e(mb_substr($task['title'], 0, 8)) ?></a>
              <?php endforeach; ?>
              <?php foreach ($dayEvents as $evt): ?>
                <button type="button" class="event-dot dot-<?= e($evt['event_type']) ?>" aria-label="<?= $canManage ? 'Editar evento: ' : 'Ver evento: ' ?><?= e($evt['title']) ?>" aria-haspopup="dialog" aria-controls="event-modal"<?= calendar_event_attrs($evt) ?> title="<?= e($evt['title']) ?>"><?= e(mb_substr($evt['title'], 0, 8)) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <?php
          $day++;
      }
      if ($day > $daysInMonth) break;
  }
  ?>
  </div>
</section>

<section class="card" style="margin-top:24px">
  <div class="events-section-head">
    <h2>Eventos de <?= e($monthNames[$month] ?? '') ?> (<?= count($events) ?>)</h2>
    <?php if ($canManage): ?>
      <button type="button" class="btn btn-sm btn-primary btn-new-event" aria-haspopup="dialog" aria-controls="event-modal" data-default-date="<?= e($defaultEventDate) ?>">+ Nuevo evento</button>
    <?php endif; ?>
  </div>
  <?php if ($events === []): ?>
    <p class="events-empty">
      No hay eventos este mes.
      <?php if ($canManage): ?>Crea el primero con el boton "Nuevo evento" o haciendo clic en un dia del calendario.<?php endif; ?>
    </p>
  <?php else: ?>
    <div class="events-list">
      <?php foreach ($events as $evt): ?>
        <div class="event-item"<?= calendar_event_attrs($evt) ?>>
          <div class="event-item-info">
            <span class="event-type-badge type-<?= e($evt['event_type']) ?>"><?= e(ProjectEvent::TYPES[$evt['event_type']] ?? $evt['event_type']) ?></span>
            <strong><?= e($evt['title']) ?></strong>
            <span class="text-muted"><?= e(date('d/m/Y', strtotime($evt['event_date']))) ?><?php if ($evt['event_time']): ?> · <?= e(substr($evt['event_time'], 0, 5)) ?> h<?php endif; ?></span>
            <span class="text-muted">por <?= e($evt['creator_name']) ?></span>
            <?php if ($evt['description']): ?><p class="event-description"><?= e($evt['description']) ?></p><?php endif; ?>
          </div>
          <?php if ($canManage): ?>
          <div class="event-actions">
            <button type="button" class="btn btn-sm btn-outline-dark event-edit">Editar</button>
            <form method="post" action="<?= e(base_url('projects/calendar.php?id=' . $projectId)) ?>" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete-event">
              <input type="hidden" name="event_id" value="<?= (int) $evt['id'] ?>">
              <button class="btn btn-sm btn-event-delete" type="submit" onclick="return confirm('¿Eliminar este evento?')">Eliminar</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Modal de eventos: crear / editar (gestores) o ver (estudiantes) -->
<dialog id="event-modal" class="modal-overlay" aria-labelledby="event-modal-title" hidden>
  <div class="modal-dialog">
    <div class="modal-head">
      <h3 id="event-modal-title">Evento</h3>
      <button type="button" class="modal-close" aria-label="Cerrar">&times;</button>
    </div>
    <div class="modal-body">
      <div id="event-modal-view" class="event-view" hidden>
        <span id="view-type" class="event-type-badge"></span>
        <h4 id="view-title"></h4>
        <p class="event-view-meta">
          <span id="view-date"></span>
          <span id="view-time"></span>
          <span id="view-creator"></span>
        </p>
        <p id="view-desc" class="event-description"></p>
      </div>
      <?php if ($canManage): ?>
      <form id="event-modal-form" method="post" action="<?= e(base_url('projects/calendar.php?id=' . $projectId)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="event-modal-action" value="create-event">
        <input type="hidden" name="event_id" id="event-modal-event-id" value="">
        <div class="form-group">
          <label for="event-title-input">Titulo</label>
          <input id="event-title-input" name="title" maxlength="150" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="event-type-input">Tipo</label>
            <select id="event-type-input" name="event_type">
              <?php foreach (ProjectEvent::FORM_TYPES as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="event-date-input">Fecha</label>
            <input type="date" id="event-date-input" name="event_date" min="<?= e($project['start_date']) ?>" max="<?= e($project['end_date']) ?>" required>
          </div>
          <div class="form-group">
            <label for="event-time-input">Hora (opcional)</label>
            <input type="time" id="event-time-input" name="event_time">
          </div>
        </div>
        <div class="form-group">
          <label for="event-desc-input">Descripcion (opcional)</label>
          <textarea id="event-desc-input" name="description" maxlength="2000" rows="3"></textarea>
        </div>
        <div class="modal-foot">
          <button type="submit" id="event-modal-submit" class="btn btn-primary">Crear evento</button>
          <button type="button" id="event-modal-delete" class="btn btn-sm btn-event-delete" hidden>Eliminar</button>
          <button type="button" class="btn btn-sm btn-outline-dark modal-cancel">Cancelar</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</dialog>

<?php require APP_PATH . '/app/views/partials/footer.php'; ?>
