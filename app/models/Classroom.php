<?php
declare(strict_types=1);
/** Lista de acceso a una clase, independiente del sistema de matrícula institucional. */
final class Classroom
{
    public static function scope(int $uid, string $alias='c'): array
    {
        return ["$alias.is_active=1 AND EXISTS(SELECT 1 FROM users ct JOIN roles cr ON cr.id=ct.role_id WHERE ct.id=$alias.teacher_id AND ct.is_active=1 AND cr.code='docente')
            AND ($alias.teacher_id=? OR EXISTS(SELECT 1 FROM classroom_members cm JOIN users cu ON cu.id=cm.student_id JOIN roles sr ON sr.id=cu.role_id
            WHERE cm.classroom_id=$alias.id AND cm.student_id=? AND cm.is_active=1 AND cu.is_active=1 AND sr.code="estudiante"))",[$uid,$uid]];
    }
    public static function find(int $id): ?array
    {
        $q=db()->prepare('SELECT c.*,CONCAT(u.first_name," ",u.last_name) AS teacher_name FROM classrooms c JOIN users u ON u.id=c.teacher_id WHERE c.id=?');
        $q->execute([$id]); return $q->fetch() ?: null;
    }
    public static function canAccess(int $id,int $uid,bool $admin=false): bool
    {
        [$where,$params]=self::scope($uid);
        $q=db()->prepare('SELECT COUNT(*) FROM classrooms c WHERE c.id=?'.($admin?'':' AND '.$where));
        $q->execute($admin?[$id]:array_merge([$id],$params)); return (int)$q->fetchColumn()===1;
    }
    public static function students(int $id): array
    {
        $q=db()->prepare('SELECT u.id,u.first_name,u.last_name FROM classroom_members cm JOIN users u ON u.id=cm.student_id JOIN roles r ON r.id=u.role_id WHERE cm.classroom_id=? AND cm.is_active=1 AND u.is_active=1 AND r.code="estudiante" ORDER BY u.last_name,u.first_name');
        $q->execute([$id]);return $q->fetchAll();
    }
    public static function studentIds(int $id): array { return array_map('intval',array_column(self::students($id),'id')); }
    public static function choices(int $uid,bool $admin=false): array
    {
        [$where,$params]=self::scope($uid);
        $q=db()->prepare('SELECT c.*,CONCAT(u.first_name," ",u.last_name) AS teacher_name FROM classrooms c JOIN users u ON u.id=c.teacher_id WHERE '.($admin?'c.is_active=1':$where).' ORDER BY c.period DESC,c.career,c.subject,c.group_name LIMIT 1000');
        $q->execute($admin?[]:$params);return $q->fetchAll();
    }
    public static function label(array $c): string { return $c['subject'].' · '.$c['group_name'].' · '.$c['period'].' · '.$c['career']; }
    public static function save(?int $id,array $data,array $students,int $actor): int
    {
        foreach (['external_code'=>100,'subject'=>150,'career'=>150,'group_name'=>80,'period'=>80] as $key=>$max) {
            $data[$key]=trim((string)($data[$key]??''));
            if ($data[$key]==='' || mb_strlen($data[$key])>$max) throw new InvalidArgumentException('Completa los datos de la clase con las longitudes indicadas.');
        }
        $teacher=(int)($data['teacher_id']??0); $semester=(int)($data['semester_number']??0);
        if (!User::isActiveWithRole($teacher,'docente') || $semester<1 || $semester>12) throw new InvalidArgumentException('Docente activo o semestre no válido.');
        $students=array_values(array_unique(array_map('intval',$students)));
        if($students){
            $marks=implode(',',array_fill(0,count($students),'?'));
            $q=db()->prepare('SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id IN ('.$marks.') AND u.is_active=1 AND r.code="estudiante"');$q->execute($students);
            if((int)$q->fetchColumn()!==count($students))throw new InvalidArgumentException('La lista contiene una cuenta que no es un estudiante activo.');
        }
        $pdo=db();$pdo->beginTransaction();
        try {
            if ($id !== null) {
                $q=$pdo->prepare('SELECT id FROM classrooms WHERE id=? FOR UPDATE');$q->execute([$id]);
                if (!$q->fetchColumn()) throw new InvalidArgumentException('Clase no encontrada.');
                $old=self::find($id);$before=self::studentIds($id);
                $q=$pdo->prepare('UPDATE classrooms SET external_code=?,subject=?,career=?,semester_number=?,group_name=?,period=?,teacher_id=?,is_active=? WHERE id=?');
            } else {
                $before=[];$old=null;
                $q=$pdo->prepare('INSERT INTO classrooms(external_code,subject,career,semester_number,group_name,period,teacher_id,is_active) VALUES(?,?,?,?,?,?,?,?)');
            }
            $values=[$data['external_code'],$data['subject'],$data['career'],$semester,$data['group_name'],$data['period'],$teacher,(int)!empty($data['is_active'])];
            if ($id !== null) $values[]=$id;
            $q->execute($values); if ($id===null) $id=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE classroom_members SET is_active=0 WHERE classroom_id=?')->execute([$id]);
            foreach(array_chunk($students,400) as $chunk){
                $sql=implode(',',array_fill(0,count($chunk),'(?,?,1)'));$args=[];
                foreach($chunk as $student){$args[]=$id;$args[]=$student;}
                $q=$pdo->prepare('INSERT INTO classroom_members(classroom_id,student_id,is_active) VALUES'.$sql.' ON CONFLICT (classroom_id,student_id) DO UPDATE SET is_active=1');$q->execute($args);
            }
            // El responsable operativo sigue al docente de la clase, nunca al administrador que edita.
            $pdo->prepare('UPDATE projects SET responsible_teacher_id=? WHERE classroom_id=?')->execute([$teacher,$id]);
            // No reactivar automáticamente equipos de quienes vuelvan a una lista.
            $pdo->prepare('DELETE FROM project_students ps USING projects p WHERE p.id=ps.project_id AND p.classroom_id=? AND NOT EXISTS(SELECT 1 FROM classroom_members cm WHERE cm.classroom_id=p.classroom_id AND cm.student_id=ps.student_id AND cm.is_active=1)')->execute([$id]);
            $q=$pdo->prepare('INSERT INTO classroom_audit(classroom_id,actor_id,action,details) VALUES(?,?,?,?)');
            $q->execute([$id,$actor,$old===null?'created':'updated',json_encode(['before'=>['teacher_id'=>$old['teacher_id']??null,'is_active'=>$old['is_active']??null,'student_ids'=>$before], 'after'=>['teacher_id'=>$teacher,'is_active'=>(int)!empty($data['is_active']),'student_ids'=>$students]],JSON_THROW_ON_ERROR)]);
            $pdo->commit();return $id;
        } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
