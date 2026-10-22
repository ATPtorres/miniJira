<?php
declare(strict_types=1);

/** Entrega in-app atomica. La clave persiste aunque el usuario borre el aviso. */
final class ReminderService
{
    public static function run(?DateTimeImmutable $today = null): int
    {
        $pdo = db();
        $lockName = 'minijira-reminders-'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,24);
        $lock = $pdo->prepare('SELECT GET_LOCK(?,0)');
        $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Ya hay una ejecucion de recordatorios activa');
        try {
            $date = ($today ?? new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
            $count = 0;
            $tasks = $pdo->prepare('SELECT t.*,p.title AS project_title FROM project_tasks t JOIN projects p ON p.id=t.project_id JOIN users u ON u.id=t.assigned_student_id
                WHERE t.due_date=? AND t.status IN ("todo","doing") AND t.deleted_at IS NULL AND p.deleted_at IS NULL AND u.is_active=1
                AND EXISTS(SELECT 1 FROM project_students ps WHERE ps.project_id=p.id AND ps.student_id=t.assigned_student_id)');
            $tasks->execute([$date]);
            foreach ($tasks as $t) {
                $count += (int) self::deliver('task:'.$t['id'].':'.$t['assigned_student_id'].':'.$date,(int)$t['assigned_student_id'],
                    'task_deadline_reminder','Tarea vence manana','La tarea "'.$t['title'].'" de "'.$t['project_title'].'" vence el '.$date.'.','projects/board.php?id='.$t['project_id']);
            }
            $events = $pdo->prepare('SELECT e.* FROM project_events e JOIN projects p ON p.id=e.project_id WHERE e.event_date=? AND e.deleted_at IS NULL AND p.deleted_at IS NULL');
            $events->execute([$date]);
            foreach ($events as $event) foreach (self::recipients((int)$event['project_id']) as $uid) {
                $count += (int) self::deliver('event:'.$event['id'].':'.$uid.':'.$date,$uid,'event_deadline_reminder','Evento manana',
                    'El evento "'.$event['title'].'" es el '.$date.'.','projects/calendar.php?id='.$event['project_id']);
            }
            $projects = $pdo->prepare('SELECT id,title FROM projects WHERE end_date=? AND deleted_at IS NULL');
            $projects->execute([$date]);
            foreach ($projects as $p) {
                $metrics = ProjectMetrics::forProject((int)$p['id']);
                if ($metrics['total'] > 0 && $metrics['done'] === $metrics['total']) continue;
                foreach (self::recipients((int)$p['id']) as $uid) {
                    $count += (int) self::deliver('project:'.$p['id'].':'.$uid.':'.$date,$uid,'project_deadline_reminder','Proyecto vence manana',
                        'El proyecto "'.$p['title'].'" vence el '.$date.'.','projects/dashboard.php?id='.$p['id']);
                }
            }
            return $count;
        } finally {
            $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        }
    }

    private static function recipients(int $projectId): array
    {
        $stmt=db()->prepare('SELECT u.id FROM users u WHERE u.is_active=1 AND (u.id=(SELECT responsible_teacher_id FROM projects WHERE id=?) OR EXISTS(SELECT 1 FROM project_students ps WHERE ps.project_id=? AND ps.student_id=u.id))');
        $stmt->execute([$projectId,$projectId]);
        return array_values(array_filter(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)),fn($id)=>Project::userCanAccess($projectId,$id)));
    }

    private static function deliver(string $key,int $uid,string $type,string $title,string $message,string $link): bool
    {
        $projectId=Notification::projectFromLink($link);
        if ($projectId===null || !Project::userCanAccess($projectId,$uid)) return false;
        $pdo=db();
        $pdo->beginTransaction();
        try {
            $stmt=$pdo->prepare('INSERT INTO notification_deliveries(dedupe_key,user_id) VALUES(?,?)');
            try { $stmt->execute([$key,$uid]); }
            catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;
                $pdo->rollBack();
                return false;
            }
            Notification::create($uid,$type,$title,$message,$link);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
