<?php
/** Reglas de negocio para proyectos, grupos y tablero Kanban. */

declare(strict_types=1);

final class ProjectController
{
    public static function create(): void
    {
        self::requireTeacher();
        [$data, $studentIds, $errors] = self::projectPayload();
        if ($errors !== []) {
            self::projectErrors($errors, 'projects/form.php');
        }

        $data['created_by_id'] = current_user_id();
        $id = Project::create($data, $studentIds);

        $me = current_user();
        $teacherName = $me['first_name'] . ' ' . $me['last_name'];
        $projectTitle = $data['title'];
        $link = 'projects/board.php?id=' . $id;

        foreach ($studentIds as $studentId) {
            Notification::create(
                (int) $studentId,
                'project_created',
                'Nuevo proyecto asignado',
                'El docente ' . $teacherName . ' te asigno al proyecto "' . $projectTitle . '"',
                $link
            );
        }

        flash_set('success', 'Proyecto creado y estudiantes asignados correctamente.');
        redirect($link);
    }

    public static function update(int $projectId): void
    {
        self::requireProjectManager($projectId);
        if (Task::isProjectExpired($projectId)) {
            flash_set('error', 'El proyecto ha vencido. No se pueden editar.');
            redirect('projects/board.php?id=' . $projectId);
        }

        $oldStudentIds = Project::studentIds($projectId);

        [$data, $studentIds, $errors] = self::projectPayload();
        if ($errors !== []) {
            self::projectErrors($errors, 'projects/form.php?id=' . $projectId);
        }

        Project::update($projectId, $data, $studentIds);

        $project = Project::find($projectId);
        $projectTitle = $project['title'];
        $link = 'projects/board.php?id=' . $projectId;

        $addedIds = array_diff($studentIds, $oldStudentIds);
        $removedIds = array_diff($oldStudentIds, $studentIds);

        foreach ($addedIds as $studentId) {
            Notification::create(
                (int) $studentId,
                'project_student_added',
                'Agregado a proyecto',
                'Fuiste agregado al proyecto "' . $projectTitle . '"',
                $link
            );
        }

        foreach ($removedIds as $studentId) {
            Notification::create(
                (int) $studentId,
                'project_student_removed',
                'Removido de proyecto',
                'Fuiste removido del proyecto "' . $projectTitle . '"',
                null
            );
        }

        flash_set('success', 'Informacion del proyecto actualizada correctamente.');
        redirect($link);
    }

    public static function delete(int $projectId): void
    {
        self::requireProjectManager($projectId);
        Project::softDelete($projectId);
        flash_set('success', 'Proyecto eliminado logicamente. Ya no sera visible para sus participantes.');
        redirect('projects/index.php');
    }

    public static function createTask(int $projectId): void
    {
        self::requireProjectManager($projectId);
        if (Task::isProjectExpired($projectId)) {
            flash_set('error', 'El proyecto ha vencido. No se pueden crear nuevas tareas.');
            redirect('projects/board.php?id=' . $projectId);
        }

        $title = post('title');
        $description = post('description');
        $studentId = (int) post('assigned_student_id');
        $dueDate = post('due_date');
        $points = (int) post('points');
        $priority = post('priority');
        $errors = [];

        if (validate_length($title, 'Titulo de la tarea', 3, 160) !== '') {
            $errors['task_title'] = validate_length($title, 'Titulo de la tarea', 3, 160);
        }
        if (mb_strlen($description) > 2000) {
            $errors['task_description'] = 'La descripcion de la tarea no puede superar 2000 caracteres.';
        }
        if ($studentId > 0 && !in_array($studentId, Project::studentIds($projectId), true)) {
            $errors['task_student'] = 'La tarea solo puede asignarse a un estudiante vinculado al proyecto.';
        }
        $project = Project::find($projectId);
        if ($dueDate !== '' && (!self::validDate($dueDate) || $dueDate < $project['start_date'] || $dueDate > $project['end_date'])) {
            $errors['task_due_date'] = 'La fecha limite debe estar dentro de las fechas del proyecto.';
        }
        if ($points < 1 || $points > 100) {
            $errors['task_points'] = 'El puntaje debe estar entre 1 y 100.';
        }
        if (!array_key_exists($priority, Task::PRIORITIES)) {
            $errors['task_priority'] = 'La prioridad no es valida.';
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            redirect('projects/board.php?id=' . $projectId);
        }
        Task::create($projectId, [
            'title' => $title, 'description' => $description, 'assigned_student_id' => $studentId,
            'points' => $points, 'priority' => $priority, 'status' => 'todo', 'due_date' => $dueDate,
        ]);

        if ($studentId > 0) {
            $project = Project::find($projectId);
            $me = current_user();
            $teacherName = $me['first_name'] . ' ' . $me['last_name'];
            Notification::create(
                $studentId,
                'task_assigned',
                'Nueva tarea asignada',
                'El docente ' . $teacherName . ' te asigno la tarea "' . $title . '" en el proyecto "' . $project['title'] . '"',
                'projects/board.php?id=' . $projectId
            );
        }

        flash_set('success', 'Tarea creada correctamente.');
        redirect('projects/board.php?id=' . $projectId);
    }

    public static function updateTaskStatus(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/board.php?id=' . $projectId);
        }
        $ajax = post('ajax') === '1';
        requireCsrf();
        $task = Task::find((int) post('task_id'));
        $status = post('status');
        if ($task === null || (int) $task['project_id'] !== $projectId || !in_array($status, Task::STATUSES, true)) {
            self::statusFail($ajax, $projectId, 'La tarea o el estado solicitado no es valido.');
        }

        $canManage = can('tasks.manage') && Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'));
        $isAssignedStudent = can('tasks.update.assigned') && (int) $task['assigned_student_id'] === current_user_id();

        // Bloqueo (vencida/calificada): solo aplica al estudiante; el docente puede mover libremente
        if (Task::isLocked((int) $task['id']) && !$canManage) {
            self::statusFail($ajax, $projectId, 'La tarea esta bloqueada: plazo vencido o ya calificada.');
        }

        if (!$canManage && !$isAssignedStudent) {
            if ($ajax) {
                self::jsonOut(['ok' => false, 'error' => 'No tienes permiso para mover esta tarea.']);
            }
            access_denied();
        }

        // Restriccion: llevar la tarea a "done" SOLO docente (dropdown y drag & drop).
        if ($isAssignedStudent && !$canManage) {
            if (!Task::canStudentTransition($task['status'], $status)) {
                self::statusFail($ajax, $projectId, 'Solo el docente puede mover la tarea a "Terminado".');
            }
        }

        $oldStatus = $task['status'];
        Task::updateStatus((int) $task['id'], $status);

        $statusLabels = ['todo' => 'Pendiente', 'doing' => 'En Proceso', 'review' => 'En Revision', 'done' => 'Terminado'];
        $project = Project::find($projectId);
        $taskTitle = $task['title'];
        $link = 'projects/board.php?id=' . $projectId;

        if ($isAssignedStudent) {
            $me = current_user();
            $studentName = $me['first_name'] . ' ' . $me['last_name'];
            Notification::create(
                (int) $project['responsible_teacher_id'],
                'task_status_changed',
                'Actualizacion de tarea',
                $studentName . ' cambio "' . $taskTitle . '" de ' . $statusLabels[$oldStatus] . ' a ' . $statusLabels[$status],
                $link
            );
        } else {
            if ((int) $task['assigned_student_id'] > 0) {
                Notification::create(
                    (int) $task['assigned_student_id'],
                    'task_status_changed',
                    'Actualizacion de tarea',
                    'Tu tarea "' . $taskTitle . '" fue cambiada de ' . $statusLabels[$oldStatus] . ' a ' . $statusLabels[$status],
                    $link
                );
            }
        }

        if ($ajax) {
            self::jsonOut(['ok' => true, 'task_id' => (int) $task['id'], 'status' => $status, 'old' => $oldStatus]);
        }
        flash_set('success', 'Estado de la tarea actualizado.');
        redirect($link);
    }

    /** Responde error de cambio de estado (JSON si es AJAX, flash+redirect si no). */
    private static function statusFail(bool $ajax, int $projectId, string $message): never
    {
        if ($ajax) {
            self::jsonOut(['ok' => false, 'error' => $message]);
        }
        flash_set('error', $message);
        redirect('projects/board.php?id=' . $projectId);
    }

    /** Salida JSON y fin de peticion. */
    private static function jsonOut(array $payload): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    /** Docente califica (review→done) o edita nota/comentario de una tarea ya calificada. */
    public static function gradeTask(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/board.php?id=' . $projectId);
        }
        requireCsrf();
        requirePermission('tasks.manage');
        if (!Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }

        $task = Task::find((int) post('task_id'));
        if ($task === null || (int) $task['project_id'] !== $projectId) {
            flash_set('error', 'La tarea no es valida.');
            redirect('projects/board.php?id=' . $projectId);
        }

        $isNewGrade = $task['status'] === 'review';
        $isEditGrade = $task['grade_result'] !== null || $task['status'] === 'done';
        if (!$isNewGrade && !$isEditGrade) {
            flash_set('error', 'Solo se pueden calificar tareas en revision o ya calificadas.');
            redirect('projects/board.php?id=' . $projectId);
        }
        // El docente no esta sujeto al bloqueo (isLocked): puede corregir notas y comentarios.

        $gradeResult = (int) post('grade_result');
        $gradeComment = trim(post('grade_comment'));
        $maxPoints = (int) $task['points'];
        if ($gradeResult < 0 || $gradeResult > $maxPoints) {
            flash_set('error', 'La calificacion debe estar entre 0 y ' . $maxPoints . '.');
            redirect('projects/board.php?id=' . $projectId);
        }

        if ($isNewGrade) {
            Task::gradeAndComplete((int) $task['id'], $gradeResult, $gradeComment);
        } else {
            Task::updateGrade((int) $task['id'], $gradeResult, $gradeComment);
        }

        if ((int) $task['assigned_student_id'] > 0) {
            Notification::create(
                (int) $task['assigned_student_id'],
                'task_graded',
                $isNewGrade ? 'Tarea calificada' : 'Calificacion actualizada',
                $isNewGrade
                    ? 'Tu tarea "' . $task['title'] . '" fue calificada: ' . $gradeResult . '/' . $task['points'] . ' pts'
                    : 'La calificacion de tu tarea "' . $task['title'] . '" fue actualizada: ' . $gradeResult . '/' . $task['points'] . ' pts',
                'projects/board.php?id=' . $projectId
            );
        }

        flash_set(
            'success',
            $isNewGrade
                ? 'Tarea calificada y marcada como completada. Nota: ' . $gradeResult . '/' . $task['points'] . ' pts.'
                : 'Calificacion y comentario actualizados. Nota: ' . $gradeResult . '/' . $task['points'] . ' pts.'
        );
        redirect('projects/board.php?id=' . $projectId);
    }

    /** CRUD de eventos del calendario. */
    public static function createEvent(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/calendar.php?id=' . $projectId);
        }
        requireCsrf();
        if (!can('tasks.manage') || !Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }

        $title = trim(post('title'));
        $description = trim(post('description'));
        $eventDate = post('event_date');
        $eventTime = post('event_time');
        $eventType = post('event_type') ?: 'custom';

        $project = Project::find($projectId);
        if ($project === null) {
            flash_set('error', 'El proyecto no existe.');
            redirect('projects/calendar.php?id=' . $projectId);
        }

        $errors = [];
        if (validate_length($title, 'Titulo del evento', 3, 150) !== '') {
            $errors['event_title'] = validate_length($title, 'Titulo del evento', 3, 150);
        }
        if (mb_strlen($description) > 2000) {
            $errors['event_description'] = 'La descripcion no puede superar 2000 caracteres.';
        }
        if (!self::validDate($eventDate)) {
            $errors['event_date'] = 'La fecha del evento no es valida.';
        } elseif ($eventDate < $project['start_date'] || $eventDate > $project['end_date']) {
            $errors['event_date'] = 'La fecha del evento debe estar dentro del rango del proyecto (' . date('d/m/Y', strtotime($project['start_date'])) . ' — ' . date('d/m/Y', strtotime($project['end_date'])) . ').';
        }
        if ($eventTime !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $eventTime) !== 1) {
            $errors['event_time'] = 'La hora del evento no es valida.';
        }
        if (!array_key_exists($eventType, ProjectEvent::TYPES)) {
            $errors['event_type'] = 'El tipo de evento no es valido.';
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            redirect('projects/calendar.php?id=' . $projectId);
        }

        ProjectEvent::create([
            'project_id' => $projectId,
            'title' => $title,
            'description' => $description,
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'event_type' => $eventType,
            'created_by_id' => current_user_id(),
        ]);

        $students = Project::students($projectId);
        $me = current_user();
        $teacherName = $me['first_name'] . ' ' . $me['last_name'];
        $eventDateFormatted = date('d/m/Y', strtotime($eventDate));
        $eventTimeText = $eventTime !== '' ? ' a las ' . date('H:i', strtotime($eventTime)) : '';

        foreach ($students as $student) {
            Notification::create(
                (int) $student['id'],
                'event_created',
                'Nuevo evento en proyecto',
                'El docente ' . $teacherName . ' creo el evento "' . $title . '" el dia ' . $eventDateFormatted . $eventTimeText . ' en el proyecto "' . $project['title'] . '"',
                'projects/calendar.php?id=' . $projectId
            );
        }

        flash_set('success', 'Evento creado correctamente.');
        redirect('projects/calendar.php?id=' . $projectId);
    }

    public static function updateEvent(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/calendar.php?id=' . $projectId);
        }
        requireCsrf();
        if (!can('tasks.manage') || !Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }

        $eventId = (int) post('event_id');
        $existing = ProjectEvent::find($eventId);
        if ($existing === null || (int) $existing['project_id'] !== $projectId) {
            flash_set('error', 'El evento no es valido.');
            redirect('projects/calendar.php?id=' . $projectId);
        }

        $title = trim(post('title'));
        $description = trim(post('description'));
        $eventDate = post('event_date');
        $eventTime = post('event_time');
        $eventType = post('event_type') ?: 'custom';

        $project = Project::find($projectId);
        if ($project === null) {
            flash_set('error', 'El proyecto no existe.');
            redirect('projects/calendar.php?id=' . $projectId);
        }

        $errors = [];
        if (validate_length($title, 'Titulo del evento', 3, 150) !== '') {
            $errors['event_title'] = validate_length($title, 'Titulo del evento', 3, 150);
        }
        if (mb_strlen($description) > 2000) {
            $errors['event_description'] = 'La descripcion no puede superar 2000 caracteres.';
        }
        if (!self::validDate($eventDate)) {
            $errors['event_date'] = 'La fecha del evento no es valida.';
        } elseif ($eventDate < $project['start_date'] || $eventDate > $project['end_date']) {
            $errors['event_date'] = 'La fecha del evento debe estar dentro del rango del proyecto (' . date('d/m/Y', strtotime($project['start_date'])) . ' — ' . date('d/m/Y', strtotime($project['end_date'])) . ').';
        }
        if ($eventTime !== '' && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $eventTime) !== 1) {
            $errors['event_time'] = 'La hora del evento no es valida.';
        }
        if (!array_key_exists($eventType, ProjectEvent::TYPES)) {
            $errors['event_type'] = 'El tipo de evento no es valido.';
        }

        if ($errors !== []) {
            flash_form_errors($errors);
            redirect('projects/calendar.php?id=' . $projectId);
        }

        ProjectEvent::update($eventId, [
            'title' => $title,
            'description' => $description,
            'event_date' => $eventDate,
            'event_time' => $eventTime,
            'event_type' => $eventType,
        ]);
        flash_set('success', 'Evento actualizado correctamente.');
        redirect('projects/calendar.php?id=' . $projectId);
    }

    public static function deleteEvent(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/calendar.php?id=' . $projectId);
        }
        requireCsrf();
        if (!can('tasks.manage') || !Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }

        $eventId = (int) post('event_id');
        $existing = ProjectEvent::find($eventId);
        if ($existing === null || (int) $existing['project_id'] !== $projectId) {
            flash_set('error', 'El evento no es valido.');
            redirect('projects/calendar.php?id=' . $projectId);
        }

        ProjectEvent::delete($eventId);
        flash_set('success', 'Evento eliminado correctamente.');
        redirect('projects/calendar.php?id=' . $projectId);
    }

    /** @return array{0:array,1:int[],2:array} */
    private static function projectPayload(): array
    {
        $data = [
            'title' => post('title'),
            'description' => post('description'),
            'objectives' => post('objectives'),
            'start_date' => post('start_date'),
            'end_date' => post('end_date'),
            'responsible_teacher_id' => current_user_id(),
        ];
        $rawStudents = $_POST['student_ids'] ?? [];
        $studentIds = is_array($rawStudents)
            ? array_values(array_unique(array_filter(array_map('intval', $rawStudents), static fn (int $id): bool => $id > 0)))
            : [];
        $errors = [];
        foreach ([['title', 'Titulo', 3, 150], ['description', 'Descripcion', 10, 5000], ['objectives', 'Objetivos', 10, 5000]] as [$key, $label, $min, $max]) {
            $error = validate_length($data[$key], $label, $min, $max);
            if ($error !== '') {
                $errors[$key] = $error;
            }
        }
        if (!self::validDate($data['start_date'])) {
            $errors['start_date'] = 'La fecha de inicio no es valida.';
        }
        if (!self::validDate($data['end_date'])) {
            $errors['end_date'] = 'La fecha limite no es valida.';
        } elseif (self::validDate($data['start_date']) && $data['end_date'] <= $data['start_date']) {
            $errors['end_date'] = 'La fecha limite debe ser posterior a la fecha de inicio.';
        }
        if ($studentIds === []) {
            $errors['student_ids'] = 'Debes asignar al menos un estudiante al proyecto.';
        } else {
            foreach ($studentIds as $studentId) {
                if (!User::isActiveWithRole($studentId, 'estudiante')) {
                    $errors['student_ids'] = 'Solo se pueden asignar estudiantes activos.';
                    break;
                }
            }
        }
        return [$data, $studentIds, $errors];
    }

    private static function projectErrors(array $errors, string $path): void
    {
        flash_form_errors($errors);
        redirect($path);
    }

    private static function requireTeacher(): void
    {
        if (!is_post()) {
            redirect('projects/index.php');
        }
        requireCsrf();
        requirePermission('projects.create');
    }

    private static function requireProjectManager(int $projectId): void
    {
        if (!is_post()) {
            redirect('projects/index.php');
        }
        requireCsrf();
        if ((!can('projects.edit.own') && !can('projects.manage.all')) || !Project::userCanManage($projectId, current_user_id(), can('projects.manage.all'))) {
            access_denied();
        }
    }

    private static function validDate(string $date): bool
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
