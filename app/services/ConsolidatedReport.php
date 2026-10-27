<?php
declare(strict_types=1);
final class ConsolidatedReport
{
    public static function build(array $input): array
    {
        $filters=Workload::filters($input);
        $uid=current_user_id(); $admin=can('projects.manage.all');
        [$scope,$params]=Project::accessScope($uid,$admin);
        [$filter,$more]=Workload::classFilter($filters);
        $params=array_merge($params,$more);
        // Teachers see only the performance of their own projects; students only their own tasks.
        $own=!$admin && !User::isActiveWithRole($uid,'docente');
        $student=$input['student_id']??'';
        if(!is_scalar($student) || ($student!=='' && !ctype_digit((string)$student))) throw new InvalidArgumentException('Estudiante no válido.');
        if($own) $student=(string)$uid;
        $where=$scope.$filter;
        if(!$own && !$admin) {$where.=' AND c.teacher_id=?';$params[]=$uid;}
        $taskFilter='';
        if($student!=='') {$taskFilter=' AND t.assigned_student_id=?';$params[]=(int)$student;}
        $sql='SELECT p.id,p.title,c.subject,c.career,c.period,c.group_name,c.semester_number,
          t.id AS task_id,t.status,t.points,t.grade_result,t.due_date,t.assigned_student_id,
          CONCAT(u.first_name," ",u.last_name) AS student_name
          FROM projects p LEFT JOIN classrooms c ON c.id=p.classroom_id
          LEFT JOIN project_tasks t ON t.project_id=p.id AND t.deleted_at IS NULL
          LEFT JOIN users u ON u.id=t.assigned_student_id
          WHERE '.$where.$taskFilter.' ORDER BY p.id,t.id';
        $q=db()->prepare($sql);$q->execute($params);
        $projects=[];$students=[];$total=self::emptyCounts();
        while($r=$q->fetch()) {
            $id=(int)$r['id'];
            if(!isset($projects[$id])) $projects[$id]=array_merge($r,self::emptyCounts());
            if($r['task_id']===null) continue;
            self::add($projects[$id],$r);self::add($total,$r);
            if($r['assigned_student_id']!==null) {
                $sid=(int)$r['assigned_student_id'];
                if(!isset($students[$sid])) $students[$sid]=array_merge(['id'=>$sid,'name'=>$r['student_name'],'project_ids'=>[]],self::emptyCounts());
                $students[$sid]['project_ids'][$id]=true;
                self::add($students[$sid],$r);
            }
        }
        foreach($projects as &$row) self::finish($row);unset($row);
        foreach($students as &$row) self::finish($row);unset($row);
        self::finish($total);
        return compact('filters','student','projects','students','total','own');
    }
    private static function emptyCounts(): array {return ['total'=>0,'done'=>0,'graded'=>0,'earned'=>0,'graded_points'=>0,'overdue'=>0];}
    private static function add(array &$a,array $r): void {
        $a['total']++;$a['done']+=(int)($r['status']==='done');
        $a['overdue']+=(int)($r['status']!=='done' && $r['due_date']!==null && $r['due_date']<date('Y-m-d'));
        if($r['grade_result']!==null) {$a['graded']++;$a['earned']+=(int)$r['grade_result'];$a['graded_points']+=(int)$r['points'];}
    }
    private static function finish(array &$a): void {
        $a['progress']=ProjectMetrics::percentage($a['done'],$a['total']);
        $a['performance']=$a['graded_points']>0?round(100*$a['earned']/$a['graded_points'],1):null;
    }
}
