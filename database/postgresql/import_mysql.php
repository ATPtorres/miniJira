<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../app/bootstrap.php';
$host=getenv('SISTEMA_MYSQL_HOST') ?: '127.0.0.1';
$port=(int)(getenv('SISTEMA_MYSQL_PORT') ?: 3306);
$name=getenv('SISTEMA_MYSQL_DATABASE') ?: 'sistema_universitario';
$user=getenv('SISTEMA_MYSQL_USERNAME') ?: 'root';
$pass=getenv('SISTEMA_MYSQL_PASSWORD') ?: '';
$source=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$target=db();
$tables=['roles','permissions','academic_periods','careers','subjects','users','role_permissions','password_reset_tokens','study_plans','study_plan_subjects','subject_prerequisites','groups','classrooms','classroom_members','classroom_audit','teaching_assignments','enrollments','course_enrollments','projects','project_students','project_tasks','project_events','project_comments','project_files','notifications','project_progress_history','notification_deliveries'];
foreach($tables as $table){
 try{$meta=$source->query("SELECT * FROM `{$table}` LIMIT 0");}
 catch(PDOException $e){if(in_array($e->getCode(),['42S02','42P01'],true)){echo "SKIP {$table}\n";continue;}throw $e;}
 $columns=[];for($i=0;$i<$meta->columnCount();$i++){$m=$meta->getColumnMeta($i);if(!empty($m['name']))$columns[]=$m['name'];}
 if(!$columns)continue;
 $order=in_array('id',$columns,true)?' ORDER BY id':'';
 $rows=$source->query("SELECT * FROM `{$table}`{$order}");
 $targetName=$table==='groups'?'"groups"':'"'.$table.'"';
 $columnSql=implode(',',array_map(static fn($c)=>'"'.$c.'"',$columns));
 $marks=implode(',',array_fill(0,count($columns),'?'));
 $insert=$target->prepare("INSERT INTO {$targetName} ({$columnSql}) VALUES ({$marks}) ON CONFLICT DO NOTHING");
 $target->beginTransaction();$count=0;
 try{while($row=$rows->fetch(PDO::FETCH_NUM)){$insert->execute($row);$count++;}$target->commit();}
 catch(Throwable $e){if($target->inTransaction())$target->rollBack();throw $e;}
 echo "{$table}: {$count} filas leidas\n";
}
foreach($tables as $table){
 $targetName=$table==='groups'?'"groups"':'"'.$table.'"';
 try{$q=$target->query("SELECT pg_get_serial_sequence('{$table}','id')");$seq=$q->fetchColumn();if($seq)$target->exec("SELECT setval('{$seq}',GREATEST(COALESCE((SELECT MAX(id) FROM {$targetName}),1),1),EXISTS(SELECT 1 FROM {$targetName}))");}
 catch(Throwable $e){}
}
echo "Importacion finalizada. Verifica conteos y relaciones antes de cambiar el trafico.\n";
