<?php
/** Poblado pequeño y repetible para pruebas manuales en pruebacritica. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../app/bootstrap.php';

$password='Clave.2026!';
$domain='demo.minijira.test';
$definitions=[
    'admin'=>['admin','Administrador','Demo'],
    'ana'=>['docente','Ana','Rojas'],
    'carlos'=>['docente','Carlos','Mendoza'],
    'lucia'=>['docente','Lucía','Vargas'],
    'diego'=>['docente','Diego','Flores'],
    'alejandro'=>['estudiante','Alejandro','Torres'],
    'jose'=>['estudiante','José','Chambi'],
    'maria'=>['estudiante','María','Quispe'],
    'sofia'=>['estudiante','Sofía','López'],
    'luis'=>['estudiante','Luis','Fernández'],
    'camila'=>['estudiante','Camila','Pérez'],
    'pedro'=>['estudiante','Pedro','Gutiérrez'],
    'valeria'=>['estudiante','Valeria','Castro'],
];
$period=date('Y').'-'.((int)date('n')<=6?'1':'2');
$classes=[
    ['SIS-PROG-A','Programación I','Ingeniería de Sistemas',1,'A',$period,'ana',['alejandro','jose','maria'],true],
    ['SIS-PROG-B','Programación I','Ingeniería de Sistemas',1,'B',$period,'ana',['sofia','luis'],true],
    ['SIS-BD-A','Bases de datos','Ingeniería de Sistemas',3,'A',$period,'carlos',['alejandro','camila','pedro'],true],
    ['ADM-CONT-C','Contabilidad I','Administración',2,'C',$period,'lucia',['jose','valeria','sofia'],true],
    ['IND-EST-D','Estadística','Ingeniería Industrial',4,'D',$period,'diego',['camila','luis','valeria'],true],
    ['ADM-GEST-A','Gestión de proyectos','Administración',4,'A',$period,'lucia',['alejandro','maria','valeria'],true],
    ['ARCHIVO-SIS','Introducción a sistemas','Ingeniería de Sistemas',1,'Histórico','Archivo '.((int)date('Y')-1),'carlos',['alejandro','pedro'],false],
];

function demoId(string $sql,array $params): ?int
{
    $q=db()->prepare($sql);$q->execute($params);$id=$q->fetchColumn();return $id===false?null:(int)$id;
}
function demoFile(int $project,int $task,int $student,string $name,string $content,?array $previous,array &$paths): int
{
    $dir=ProjectFile::projectDir($project);
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir))throw new RuntimeException('No se pudo preparar el directorio de entregas demo.');
    $stored=bin2hex(random_bytes(16)).'.txt';$path=$dir.'/'.$stored;
    if(file_put_contents($path,$content)===false)throw new RuntimeException('No se pudo crear el entregable demo.');
    $paths[]=$path;
    return ProjectFile::create([
        'project_id'=>$project,'task_id'=>$task,'uploaded_by_id'=>$student,
        'original_name'=>$name,'stored_name'=>$stored,'mime_type'=>'text/plain','file_size'=>strlen($content),
        'description'=>'Entrega sintética para pruebas manuales.','category'=>'other',
        'version_root_id'=>$previous['id']??null,'version_number'=>$previous===null?1:2,
    ]);
}

$lockName='minijira-demo-'.substr(hash('sha256',(string)db()->query('SELECT DATABASE()')->fetchColumn()),0,24);
$q=db()->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lockName]);
if((int)$q->fetchColumn()!==1){fwrite(STDERR,"Ya hay un poblado demo ejecutándose.\n");exit(1);}
$users=[];$classIds=[];$projects=[];$newUsers=0;$newClasses=0;$newProjects=0;
try {
    foreach($definitions as $key=>[$role,$first,$last]) {
        $email=$key.'@'.$domain;$existing=User::findByEmail($email);
        if($existing){
            if($existing['role_code']!==$role || !password_verify($password,$existing['password_hash'])) {
                throw new RuntimeException('La cuenta '.$email.' ya existe con otro rol o contraseña. Se conservó sin cambios.');
            }
            $users[$key]=(int)$existing['id'];
        } else {
            $users[$key]=User::create(['first_name'=>$first,'last_name'=>$last,'email'=>$email,'role_code'=>$role,'password_hash'=>password_hash($password,password_algo())]);
            $newUsers++;
        }
    }
    foreach($classes as [$code,$subject,$career,$semester,$group,$term,$teacher,$members,$active]) {
        $external='DEMO-'.$code;
        $classId=demoId('SELECT id FROM classrooms WHERE external_code=?',[$external]);
        $studentIds=array_map(fn($key)=>$users[$key],$members);
        if($classId===null){
            $classId=Classroom::save(null,['external_code'=>$external,'subject'=>$subject,'career'=>$career,'semester_number'=>$semester,'group_name'=>$group,'period'=>$term,'teacher_id'=>$users[$teacher],'is_active'=>1],$studentIds,$users['admin']);
            $newClasses++;
        }
        $classIds[$code]=$classId;
        $title='DEMO · '.$subject.' · Grupo '.$group;
        $project=demoId('SELECT id FROM projects WHERE classroom_id=? AND title=? ORDER BY id LIMIT 1',[$classId,$title]);
        if($project!==null){$projects[$code]=$project;continue;}
        $class=Classroom::find($classId);
        if(!(int)$class['is_active']){fwrite(STDOUT,"Clase $external inactiva: se conservó sin crear proyectos nuevos.\n");continue;}
        $project=Project::create([
            'classroom_id'=>$classId,'title'=>$title,
            'description'=>'Proyecto sintético de '.$subject.' para probar tareas, entregas, aislamiento y reportes.',
            'objectives'=>'Revisar permisos de clase, participar en comentarios y evaluar entregables de prueba.',
            'start_date'=>date('Y-m-d',strtotime('-10 days')),'end_date'=>date('Y-m-d',strtotime('+30 days')),
            'responsible_teacher_id'=>$users[$teacher],'created_by_id'=>$users['admin'],
        ],$studentIds);
        $paths=[];db()->beginTransaction();
        try {
            $taskIds=[];
            $taskDefinitions=[
                ['Investigar y planificar','todo',3],
                ['Desarrollar el trabajo','doing',7],
                ['Revisar la entrega','review',10],
                ['Trabajo evaluado','done',-1],
            ];
            foreach($taskDefinitions as $index=>[$label,$state,$days]){
                $student=$studentIds[$index%count($studentIds)];
                $due=($index===0 && in_array($code,['SIS-BD-A','IND-EST-D'],true))?-2:$days;
                Task::create($project,['title'=>$label,'description'=>'Actividad demo de '.$subject.'. Prueba sus estados, comentarios y archivos sin usar información real.','assigned_student_id'=>$student,'points'=>10,'priority'=>$index===0?'high':'medium','status'=>$state,'due_date'=>date('Y-m-d',strtotime(($due>=0?'+':'').$due.' days'))]);
                $task=demoId('SELECT MAX(id) FROM project_tasks WHERE project_id=?',[$project]);$taskIds[]=$task;
                if($state==='review'){
                    $first=demoFile($project,$task,$student,'entrega-demo.txt',"Primera versión de la entrega demo de $subject.\n",null,$paths);
                    demoFile($project,$task,$student,'entrega-demo-v2.txt',"Segunda versión: entrega corregida de $subject.\n",['id'=>$first],$paths);
                }
                if($state==='done'){
                    Task::gradeAndComplete($task,8,'Buen trabajo. Esta evaluación es de ejemplo: 8 de 10 puntos.');
                    demoFile($project,$task,$student,'trabajo-evaluado.txt',"Trabajo demo evaluado de $subject.\n",null,$paths);
                }
            }
            $root=ProjectComment::create(['project_id'=>$project,'task_id'=>$taskIds[2],'author_id'=>$users[$teacher],'body'=>'Revisa la segunda versión del archivo y explica los cambios realizados.','visibility'=>'project']);
            ProjectComment::create(['project_id'=>$project,'task_id'=>$taskIds[2],'parent_id'=>$root,'author_id'=>$studentIds[2%count($studentIds)],'body'=>'Adjunté la versión corregida. Gracias por la revisión.','visibility'=>'project']);
            ProjectComment::create(['project_id'=>$project,'author_id'=>$studentIds[0],'body'=>'Este proyecto contiene datos de demostración para explorar la plataforma.','visibility'=>'project']);
            ProjectEvent::create(['project_id'=>$project,'title'=>'Revisión del proyecto demo','description'=>'Evento sintético para probar el calendario.','event_date'=>date('Y-m-d',strtotime('+5 days')),'event_time'=>'10:00','event_type'=>'milestone','created_by_id'=>$users[$teacher]]);
            foreach($studentIds as $student)Notification::create($student,'project_created','Proyecto demo asignado','Ya puedes explorar el proyecto de '.$subject.'.','projects/board.php?id='.$project);
            Notification::create($users[$teacher],'file_uploaded','Entrega demo por revisar','Hay una entrega de ejemplo con dos versiones.','projects/task.php?project_id='.$project.'&task_id='.$taskIds[2]);
            if(!$active){
                $q=db()->prepare('UPDATE classrooms SET is_active=0 WHERE id=?');$q->execute([$classId]);
                $q=db()->prepare('INSERT INTO classroom_audit(classroom_id,actor_id,action,details) VALUES(?,?,?,?)');
                $q->execute([$classId,$users['admin'],'demo_inactive','Clase histórica de demostración.']);
            }
            db()->commit();$projects[$code]=$project;$newProjects++;
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack();
            foreach($paths as $path)if(is_file($path))unlink($path);
            $q=db()->prepare('DELETE FROM projects WHERE id=?');$q->execute([$project]);throw $e;
        }
    }
    $tasks=0;$files=0;
    foreach($projects as $project){
        $q=db()->prepare('SELECT COUNT(*) FROM project_tasks WHERE project_id=?');$q->execute([$project]);$tasks+=(int)$q->fetchColumn();
        $q=db()->prepare('SELECT COUNT(*) FROM project_files WHERE project_id=?');$q->execute([$project]);$files+=(int)$q->fetchColumn();
    }
    fwrite(STDOUT,"Poblado listo: ".count($users)." cuentas, ".count($classIds)." clases, ".count($projects)." proyectos, $tasks tareas y $files versiones de archivos.\n");
    fwrite(STDOUT,"Nuevos en esta ejecución: $newUsers cuentas, $newClasses clases, $newProjects proyectos.\n");
    fwrite(STDOUT,"Las cuentas existentes fuera de @demo.minijira.test y sus contraseñas se conservaron.\n");
    foreach($definitions as $key=>[$role,$first,$last])fwrite(STDOUT,"$role\t$key@$domain\t$first $last\n");
}catch(Throwable $e){fwrite(STDERR,'Poblado detenido: '.$e->getMessage()."\n");exit(1);}
finally{$q=db()->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lockName]);}
