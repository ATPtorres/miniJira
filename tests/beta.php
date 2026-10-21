<?php
/** Integracion contra una BD aislada: SISTEMA_DB_DATABASE=miniJira_beta_test php tests/beta.php */
declare(strict_types=1);
$name=getenv('SISTEMA_DB_DATABASE') ?: '';
if (!preg_match('/^[A-Za-z0-9_]+_test$/',$name)) { fwrite(STDERR,"Usa una BD dedicada cuyo nombre termine en _test.\n"); exit(1); }
require __DIR__.'/../app/bootstrap.php';
if (db()->query('SELECT DATABASE()')->fetchColumn() !== $name) throw new RuntimeException('BD de pruebas incorrecta');
// Only the explicitly selected test database is initialized/reset.
$sql=file_get_contents(__DIR__.'/../database/install.sql');
$sql=preg_replace('/CREATE DATABASE IF NOT EXISTS sistema_universitario.*?;/s','',$sql);
$sql=str_replace('USE sistema_universitario;','',$sql);
db()->exec($sql);
db()->exec('DELETE FROM projects');
db()->exec('DELETE FROM users');
$pass=0;$fail=0;
function check(bool $ok,string $message='Comprobacion fallida'): void { if (!$ok) throw new RuntimeException($message); }
function test(string $name,callable $fn): void { global $pass,$fail; try { $fn(); $pass++; echo "PASS $name\n"; } catch(Throwable $e) { $fail++; echo "FAIL $name: ".$e->getMessage()."\n"; } }
function query(string $sql,array $params=[]): mixed { $s=db()->prepare($sql);$s->execute($params);return $s->fetchColumn(); }
function execute(string $sql,array $params=[]): void { $s=db()->prepare($sql);$s->execute($params); }
$users=[];
foreach(['admin','docente','estudiante','outsider','observer'] as $role) {
    $users[$role]=User::create(['first_name'=>$role==='docente'?'Álvaro':ucfirst($role),'last_name'=>'Beta','email'=>$role.'@beta.test','password_hash'=>password_hash('Beta.Test2026!',PASSWORD_DEFAULT),'role_code'=>in_array($role,['outsider','observer'],true)?'estudiante':$role]);
}
$student=$users['estudiante'];$teacher=$users['docente'];
$start=date('Y-m-d');$end=date('Y-m-d',strtotime('+30 days'));
$project=Project::create(['title'=>'Proyecto Beta de prueba','description'=>'Descripcion de prueba con acentos: investigación','objectives'=>'Validar avance y notificaciones','start_date'=>$start,'end_date'=>$end,'responsible_teacher_id'=>$teacher,'created_by_id'=>$teacher],[$student,$users['observer']]);
function addTask(int $project,string $status='todo',?string $due=null): int {
    global $student;
    Task::create($project,['title'=>'Tarea Beta','description'=>'Prueba <script>alert(1)</script>','assigned_student_id'=>$student,'points'=>10,'priority'=>'medium','status'=>$status,'due_date'=>$due ?: date('Y-m-d',strtotime('+7 days'))]);
    return (int)query('SELECT MAX(id) FROM project_tasks WHERE project_id=?',[$project]);
}
test('Sin tareas: avance 0 y baseline real',function() use($project){ check(ProjectMetrics::forProject($project)['progress']===0);check((int)query('SELECT COUNT(*) FROM project_progress_history WHERE project_id=?',[$project])===1); });
$t1=addTask($project);$t2=addTask($project);
test('Dos pendientes: avance 0',fn()=>check(ProjectMetrics::forProject($project)['progress']===0));
Task::updateStatus($t1,'done');
test('Una de dos terminadas: avance 50',fn()=>check(ProjectMetrics::forProject($project)['progress']===50));
Task::gradeAndComplete($t2,8,'Buen trabajo');
test('Todas terminadas: avance 100 y final',function()use($project){$m=ProjectMetrics::forProject($project);check($m['progress']===100 && $m['completed']);});
Task::updateStatus($t1,'doing');
test('Reabrir tarea reduce el avance',fn()=>check(ProjectMetrics::forProject($project)['progress']===50));
Task::deleteTask($t1);
test('Borrado logico excluido del denominador',function()use($project){$m=ProjectMetrics::forProject($project);check($m['total']===1 && $m['progress']===100);});
test('Porcentaje redondeado no declara final prematuro',fn()=>check(!ProjectMetrics::fromCounts(['done'=>199,'todo'=>1])['completed']));
test('Desglose estudiantil coincide con proyecto',function()use($project){$s=ProjectMetrics::students($project)[0];check($s['progress']===100 && (int)$s['total']===1);});
test('Historial solo contiene observaciones reales del dia',function()use($project){$h=ProjectMetrics::history($project,date('Y-m-d'),date('Y-m-d'));check(count($h)===1 && $h[0]['progress']===100);});
test('Sin historial previo inventado',fn()=>check(ProjectMetrics::history($project,'2000-01-01','2000-01-31')===[]));
test('Cambio de tarea e historial se revierten juntos',function()use($project){
    $before=(int)query('SELECT COUNT(*) FROM project_tasks WHERE project_id=?',[$project]);
    db()->exec("CREATE TRIGGER beta_fail_history BEFORE INSERT ON project_progress_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fallo simulado'");
    try { try { addTask($project); throw new RuntimeException('Debio fallar'); } catch(PDOException $expected) {} }
    finally { db()->exec('DROP TRIGGER beta_fail_history'); }
    check((int)query('SELECT COUNT(*) FROM project_tasks WHERE project_id=?',[$project])===$before);
});
test('Proyecto futuro tiene avance temporal 0',fn()=>check(ProjectMetrics::timeline(['start_date'=>'2030-01-01','end_date'=>'2030-01-05'],new DateTimeImmutable('2029-12-31'))['progress']===0));
test('Vencido tiene avance temporal 100 y cero dias restantes',function(){ $t=ProjectMetrics::timeline(['start_date'=>'2020-01-01','end_date'=>'2020-01-05'],new DateTimeImmutable('2020-01-07'));check($t['progress']===100 && $t['expired'] && $t['days_remaining']===0); });
test('Fecha limite incluye el dia completo',fn()=>check(!ProjectMetrics::timeline(['start_date'=>'2020-01-01','end_date'=>'2020-01-05'],new DateTimeImmutable('2020-01-05 23:00:00'))['expired']));
test('Fechas de filtro invalidas rechazadas',function(){try{ProjectMetrics::dateRange('2026-02-31','2026-03-01');}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Acepto fecha invalida');});
test('Miembros y usuarios externos separados',function()use($project,$student,$users){check(Project::userCanAccess($project,$student));check(!Project::userCanAccess($project,$users['outsider']));});
$_SESSION['user_id']=$teacher;$_SESSION['role_code']='docente';
$tomorrow=date('Y-m-d',strtotime('+1 day'));
$rproject=Project::create(['title'=>'Proyecto recordatorios','description'=>'Prueba recordatorios automaticos','objectives'=>'Evitar duplicados y ejecuciones simultaneas','start_date'=>$start,'end_date'=>$tomorrow,'responsible_teacher_id'=>$teacher,'created_by_id'=>$teacher],[$student]);
$rtask=addTask($rproject,'todo',$tomorrow);
addTask($rproject,'done',$tomorrow);addTask($rproject,'review',$tomorrow);$deleted=addTask($rproject,'todo',$tomorrow);Task::deleteTask($deleted);
ProjectEvent::create(['project_id'=>$rproject,'title'=>'Hito de prueba','description'=>'Prueba','event_date'=>$tomorrow,'event_time'=>'10:00','event_type'=>'milestone','created_by_id'=>$teacher]);
test('Recordatorios: tarea, evento y proyecto con destinatarios correctos',fn()=>check(ReminderService::run()===5));
test('Repetir ejecucion no duplica avisos',fn()=>check(ReminderService::run()===0));
test('Borrar aviso no permite duplicar entrega',function()use($student){$id=(int)query('SELECT id FROM notifications WHERE user_id=? LIMIT 1',[$student]);Notification::delete($id,$student);check(ReminderService::run()===0);});
test('Candado evita ejecuciones simultaneas',function(){
    $c=config('db');$other=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.getenv('SISTEMA_DB_DATABASE'),$c['username'],$c['password']);
    $name='minijira-reminders-'.substr(hash('sha256',getenv('SISTEMA_DB_DATABASE')),0,24);
    $s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute([$name]);
    try { try{ReminderService::run();}catch(RuntimeException $e){check(str_contains($e->getMessage(),'activa'));return;}throw new RuntimeException('No bloqueo'); }
    finally {$s=$other->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$name]);}
});
test('Postergar tarea permite recordatorio de la nueva fecha',function()use($rtask){execute('UPDATE project_tasks SET due_date=? WHERE id=?',[date('Y-m-d',strtotime('+2 days')),$rtask]);check(ReminderService::run(new DateTimeImmutable('tomorrow'))===1);});
test('Notificaciones aisladas por usuario',function()use($student,$users){Notification::create($student,'comment_added','Privado','Mensaje privado');check(Notification::feed($users['outsider'])['unread']===0);});
test('No puede marcar o borrar notificaciones ajenas',function()use($student,$users){$id=Notification::create($student,'comment_added','Solo destinatario','Mensaje');Notification::markRead($id,$users['outsider']);Notification::delete($id,$users['outsider']);check((int)query('SELECT is_read FROM notifications WHERE id=?',[$id])===0);});
test('Version del feed cambia al marcar leido',function()use($student){$a=Notification::feed($student);Notification::markAllRead($student);$b=Notification::feed($student);check($a['version']!==$b['version'] && $b['unread']===0);});
test('Render SVG no inventa historial y aporta tabla accesible',function()use($project){$html=ProjectCharts::history(ProjectMetrics::history($project,date('Y-m-d'),date('Y-m-d')));check(str_contains($html,'<svg') && str_contains($html,'<table'));});
test('Reporte genera PDF real con varias paginas',function()use($project){for($i=0;$i<45;$i++)addTask($project);$pdf=ProjectReport::render($project);check(str_starts_with($pdf,'%PDF-'));check(strlen($pdf)>10000);file_put_contents(sys_get_temp_dir().'/minijira-beta-test.pdf',$pdf);});
// Restore a simple project for HTTP/browser tests; all fixtures remain in the dedicated test DB.
foreach (db()->query('SELECT id FROM project_tasks WHERE project_id='.$project.' AND deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) as $id) Task::deleteTask((int)$id);
$t1=addTask($project);$t2=addTask($project);
file_put_contents(sys_get_temp_dir().'/minijira-beta-fixtures.json',json_encode(['project'=>$project,'task'=>$t1,'student'=>$student,'teacher'=>$teacher,'outsider'=>$users['outsider']]));
echo "RESULT $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
