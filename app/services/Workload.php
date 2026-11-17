<?php
declare(strict_types=1);
final class Workload
{
    public const FIELDS=['period'=>'Período','career'=>'Carrera','subject'=>'Materia','semester_number'=>'Semestre','group_name'=>'Grupo'];
    public const STATES=['todo'=>'Pendiente','doing'=>'En proceso','review'=>'Entregado / por evaluar','done'=>'Terminado','graded'=>'Evaluado','overdue'=>'Vencido'];
    public static function filters(array $input): array
    {
        $out=[];
        foreach (array_merge(array_keys(self::FIELDS),['classroom_id','status']) as $key) {
            $value=$input[$key]??'';
            if (!is_scalar($value) || mb_strlen((string)$value)>150) throw new InvalidArgumentException('Filtro no válido.');
            $out[$key]=trim((string)$value);
        }
        if ($out['status']!=='' && !isset(self::STATES[$out['status']])) throw new InvalidArgumentException('Estado no válido.');
        if ($out['classroom_id']!=='' && !ctype_digit($out['classroom_id'])) throw new InvalidArgumentException('Clase no válida.');
        return $out;
    }
    public static function classFilter(array $filters,string $alias='c'): array
    {
        $sql='';$params=[];
        foreach (array_keys(self::FIELDS) as $key) if (($filters[$key]??'')!=='') {$sql.=" AND $alias.$key=?";$params[]=$filters[$key];}
        if (($filters['classroom_id']??'')!=='') {$sql.=" AND $alias.id=?";$params[]=(int)$filters['classroom_id'];}
        return [$sql,$params];
    }
    public static function options(int $uid,bool $admin,array $filters): array
    {
        [$scope,$params]=Classroom::scope($uid);
        if ($admin) {$scope='1=1';$params=[];}
        $result=[];$parent=[];
        foreach (self::FIELDS as $key=>$label) {
            [$filter,$more]=self::classFilter($parent);
            $q=db()->prepare("SELECT DISTINCT c.$key FROM classrooms c WHERE $scope $filter ORDER BY c.$key LIMIT 200");
            $q->execute(array_merge($params,$more));$result[$key]=$q->fetchAll(PDO::FETCH_COLUMN);
            if (($filters[$key]??'')!=='') $parent[$key]=$filters[$key];
        }
        return $result;
    }
    public static function page(int $uid,bool $admin,bool $own,array $filters,int $page=1): array
    {
        [$scope,$params]=Project::accessScope($uid,$admin);
        [$filter,$more]=self::classFilter($filters);
        $where=$scope.$filter.' AND t.deleted_at IS NULL';$params=array_merge($params,$more);
        if ($own) {$where.=' AND t.assigned_student_id=?';$params[]=$uid;}
        $status=$filters['status']??'';
        if ($status==='overdue') $where.=' AND t.status<>'done' AND t.due_date<CURRENT_DATE';
        elseif ($status==='graded') $where.=' AND t.grade_result IS NOT NULL';
        elseif ($status!=='') {$where.=' AND t.status=?';$params[]=$status;}
        $from=' FROM project_tasks t JOIN projects p ON p.id=t.project_id JOIN classrooms c ON c.id=p.classroom_id LEFT JOIN users u ON u.id=t.assigned_student_id WHERE '.$where;
        $q=db()->prepare('SELECT COUNT(*) AS total,COUNT(*) FILTER (WHERE t.status IN ('todo','doing')) AS pending,COUNT(*) FILTER (WHERE t.status='review') AS review,COUNT(*) FILTER (WHERE t.grade_result IS NOT NULL) AS graded'.$from);
        $q->execute($params);$counts=array_map('intval',$q->fetch());$pages=max(1,(int)ceil($counts['total']/25));$page=max(1,min($page,$pages));
        $q=db()->prepare('SELECT t.*,p.title AS project_title,c.subject,c.career,c.semester_number,c.group_name,c.period,CONCAT(u.first_name, ' ', u.last_name) AS student_name'.$from.' ORDER BY t.due_date IS NULL,t.due_date,t.id LIMIT 25 OFFSET '.(($page-1)*25));
        $q->execute($params);return ['items'=>$q->fetchAll(),'counts'=>$counts,'page'=>$page,'pages'=>$pages];
    }
}
