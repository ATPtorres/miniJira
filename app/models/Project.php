<?php
/** Persistencia de proyectos y sus grupos de estudiantes. */

declare(strict_types=1);

final class Project
{
    /** Política común de proyectos: contexto activo + equipo autorizado. */
    public static function accessScope(int $userId, bool $allProjects=false, string $alias='p'): array
    {
        if ($allProjects) return ["$alias.deleted_at IS NULL",[]];
        [$scope,$params]=Classroom::scope($userId,'ac');
        return ["$alias.deleted_at IS NULL AND EXISTS(SELECT 1 FROM classrooms ac WHERE ac.id=$alias.classroom_id AND $scope
            AND (ac.teacher_id=? OR EXISTS(SELECT 1 FROM project_students psx WHERE psx.project_id=$alias.id AND psx.student_id=?)))",array_merge($params,[$userId,$userId])];
    }
    public static function find(int $id): ?array
    {
        $q=db()->prepare('SELECT p.*,CONCAT(u.first_name," ",u.last_name) AS teacher_name,
            c.subject,c.career,c.group_name,c.period,c.semester_number,
            (SELECT COUNT(*) FROM project_students ps WHERE ps.project_id=p.id) AS student_count
            FROM projects p JOIN users u ON u.id=p.responsible_teacher_id LEFT JOIN classrooms c ON c.id=p.classroom_id WHERE p.id=?');
        $q->execute([$id]);return $q->fetch() ?: null;
    }
    public static function accessibleTo(int $userId,bool $allProjects=false,?int $limit=null,int $offset=0,array $filters=[]): array
    {
        [$scope,$params]=self::accessScope($userId,$allProjects);
        [$filter,$filterParams]=Workload::classFilter($filters,'c');
        $sql='SELECT p.*,CONCAT(u.first_name," ",u.last_name) AS teacher_name,c.subject,c.career,c.group_name,c.period,c.semester_number,
            (SELECT COUNT(*) FROM project_students ps WHERE ps.project_id=p.id) AS student_count,
            COALESCE(t.todo_count,0) AS todo_count,COALESCE(t.doing_count,0) AS doing_count,COALESCE(t.review_count,0) AS review_count,COALESCE(t.done_count,0) AS done_count
            FROM projects p JOIN users u ON u.id=p.responsible_teacher_id LEFT JOIN classrooms c ON c.id=p.classroom_id
            LEFT JOIN (SELECT project_id,COUNT(*) FILTER (WHERE status='todo') AS todo_count,COUNT(*) FILTER (WHERE status='doing') AS doing_count,COUNT(*) FILTER (WHERE status='review') AS review_count,COUNT(*) FILTER (WHERE status='done') AS done_count FROM project_tasks WHERE deleted_at IS NULL GROUP BY project_id) t ON t.project_id=p.id
            WHERE '.$scope.$filter.' ORDER BY p.end_date,p.id';
        if ($limit!==null) $sql.=' LIMIT '.max(1,min(1000,$limit)).' OFFSET '.max(0,$offset);
        $q=db()->prepare($sql);$q->execute(array_merge($params,$filterParams));return $q->fetchAll();
    }

    public static function create(array $data, array $studentIds): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $class = self::validateClass($data, $studentIds);
            $data['responsible_teacher_id'] = (int)$class['teacher_id'];
            $stmt = $pdo->prepare(
                'INSERT INTO projects (title, description, objectives, start_date, end_date, responsible_teacher_id, created_by_id, classroom_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $data['title'], $data['description'], $data['objectives'], $data['start_date'], $data['end_date'],
                $data['responsible_teacher_id'], $data['created_by_id'], $class['id'],
            ]);
            $id = (int) $pdo->lastInsertId();
            self::syncStudents($id, $studentIds, $pdo);
            ProjectMetrics::record($id);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $projectId,array $data,array $studentIds): void
    {
        $pdo=db();$pdo->beginTransaction();
        try {
            $class=self::validateClass($data,$studentIds);
            $old=self::find($projectId);
            if (!$old || ($old['classroom_id']!==null && (int)$old['classroom_id']!==(int)$class['id'])) throw new InvalidArgumentException('No se puede mover un proyecto entre clases.');
            $q=$pdo->prepare('UPDATE projects SET title=?,description=?,objectives=?,start_date=?,end_date=?,responsible_teacher_id=?,classroom_id=? WHERE id=? AND deleted_at IS NULL');
            $q->execute([$data['title'],$data['description'],$data['objectives'],$data['start_date'],$data['end_date'],$class['teacher_id'],$class['id'],$projectId]);
            self::syncStudents($projectId,$studentIds,$pdo);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private static function validateClass(array $data,array $students): array
    {
        $q=db()->prepare('SELECT * FROM classrooms WHERE id=? AND is_active=1 FOR UPDATE');$q->execute([(int)($data['classroom_id']??0)]);
        $class=$q->fetch();
        if (!$class || !User::isActiveWithRole((int)$class['teacher_id'],'docente')) throw new InvalidArgumentException('Selecciona una clase activa con docente autorizado.');
        if(PHP_SAPI!=='cli' && !can('projects.manage.all') && (int)$class['teacher_id']!==current_user_id()) {
            db()->rollBack();access_denied();
        }
        if (array_diff(array_map('intval',$students),Classroom::studentIds((int)$class['id']))) throw new InvalidArgumentException('El equipo contiene participantes ajenos a la clase.');
        return $class;
    }

    /** Serializa las escrituras con bajas y cambios de responsable de la clase. */
    public static function managedMutation(int $id,callable $operation,string $permission='tasks.manage'): mixed
    {
        $pdo=db();$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT id FROM projects WHERE id=? AND deleted_at IS NULL FOR UPDATE');$q->execute([$id]);
            if(!$q->fetchColumn())throw new RuntimeException('Proyecto no disponible.');
            if(PHP_SAPI!=='cli' && ((!can($permission) && !($permission==='projects.edit.own' && can('projects.manage.all'))) || !self::userCanManage($id,current_user_id(),can('projects.manage.all')))) {
                $pdo->rollBack();access_denied();
            }
            $result=$operation();if($owns)$pdo->commit();return $result;
        }catch(Throwable $e){if($owns && $pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function softDelete(int $projectId): void
    {
        self::managedMutation($projectId,static function()use($projectId):void{
            $q=db()->prepare('UPDATE projects SET deleted_at=CURRENT_TIMESTAMP WHERE id=? AND deleted_at IS NULL');$q->execute([$projectId]);
        },'projects.edit.own');
    }

    /** @return array<int, array{id:int,first_name:string,last_name:string}> */
    public static function students(int $projectId): array
    {
        $stmt = db()->prepare(
            'SELECT u.id, u.first_name, u.last_name FROM project_students ps
             JOIN users u ON u.id = ps.student_id JOIN projects p ON p.id=ps.project_id
             JOIN classroom_members cm ON cm.classroom_id=p.classroom_id AND cm.student_id=u.id AND cm.is_active=1
             WHERE u.is_active=1 AND EXISTS(SELECT 1 FROM roles r WHERE r.id=u.role_id AND r.code="estudiante") AND ps.project_id = ? ORDER BY u.first_name, u.last_name'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** @return int[] */
    public static function studentIds(int $projectId): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], self::students($projectId));
    }

    public static function userCanAccess(int $projectId,int $userId,bool $allProjects=false): bool
    {
        [$scope,$params]=self::accessScope($userId,$allProjects);
        $q=db()->prepare('SELECT COUNT(*) FROM projects p WHERE p.id=? AND '.$scope);$q->execute(array_merge([$projectId],$params));return (int)$q->fetchColumn()===1;
    }
    public static function userCanManage(int $projectId,int $userId,bool $allProjects=false): bool
    {
        if (!self::userCanAccess($projectId,$userId,$allProjects)) return false;
        if ($allProjects) return true;
        $q=db()->prepare('SELECT COUNT(*) FROM projects p JOIN classrooms c ON c.id=p.classroom_id WHERE p.id=? AND c.teacher_id=?');$q->execute([$projectId,$userId]);return (int)$q->fetchColumn()===1;
    }
    public static function summaryFor(int $userId,bool $allProjects=false,array $filters=[]): array
    {
        [$scope,$params]=self::accessScope($userId,$allProjects);
        [$filter,$fparams]=Workload::classFilter($filters,'c');
        $q=db()->prepare('SELECT COUNT(*) AS projects,COALESCE(SUM(t.todo),0) AS todo,COALESCE(SUM(t.doing),0) AS doing,COALESCE(SUM(t.review),0) AS review,COALESCE(SUM(t.done),0) AS done
            FROM projects p LEFT JOIN classrooms c ON c.id=p.classroom_id LEFT JOIN (SELECT project_id,COUNT(*) FILTER (WHERE status='todo') AS todo,COUNT(*) FILTER (WHERE status='doing') AS doing,COUNT(*) FILTER (WHERE status='review') AS review,COUNT(*) FILTER (WHERE status='done') AS done FROM project_tasks WHERE deleted_at IS NULL GROUP BY project_id) t ON t.project_id=p.id WHERE '.$scope.$filter);
        $q->execute(array_merge($params,$fparams));return array_map('intval',$q->fetch());
    }
    public static function progressBatch(array $ids,int $uid,bool $admin): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>$id>0)));
        if(!$ids || count($ids)>25)throw new InvalidArgumentException('Máximo 25 proyectos por consulta.');
        [$scope,$params]=self::accessScope($uid,$admin);$marks=implode(',',array_fill(0,count($ids),'?'));
        $q=db()->prepare('SELECT p.id,p.start_date,p.end_date,COUNT(t.id) FILTER (WHERE t.status='todo') AS todo,COUNT(t.id) FILTER (WHERE t.status='doing') AS doing,COUNT(t.id) FILTER (WHERE t.status='review') AS review,COUNT(t.id) FILTER (WHERE t.status='done') AS done FROM projects p LEFT JOIN project_tasks t ON t.project_id=p.id AND t.deleted_at IS NULL WHERE '.$scope.' AND p.id IN ('.$marks.') GROUP BY p.id');
        $q->execute(array_merge($params,$ids));$out=[];
        foreach($q->fetchAll() as $row)$out[]=['id'=>(int)$row['id'],'metrics'=>ProjectMetrics::fromCounts($row),'timeline'=>ProjectMetrics::timeline($row)];
        return $out;
    }

    private static function syncStudents(int $projectId, array $studentIds, PDO $pdo): void
    {
        $pdo->prepare('DELETE FROM project_students WHERE project_id = ?')->execute([$projectId]);
        $stmt = $pdo->prepare('INSERT INTO project_students (project_id, student_id) VALUES (?, ?)');
        foreach (array_unique($studentIds) as $studentId) {
            $stmt->execute([$projectId, $studentId]);
        }
    }
}
