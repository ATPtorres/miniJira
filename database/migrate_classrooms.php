<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/bootstrap.php';
$pdo=db();
foreach ([['projects','classroom_id','INTEGER NULL'],['notifications','project_id','INTEGER NULL']] as [$table,$column,$type]) {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=current_schema() AND table_name=? AND column_name=?');
    $q->execute([$table,$column]);
    if (!(int)$q->fetchColumn()) $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$type}");
}
foreach ([
 ['projects','fk_projects_class','FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT'],
 ['notifications','fk_notif_project','FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE'],
] as [$table,$name,$definition]) {
 $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=current_schema() AND table_name=? AND constraint_name=?');
 $q->execute([$table,$name]);
 if (!(int)$q->fetchColumn()) $pdo->exec("ALTER TABLE {$table} ADD CONSTRAINT {$name} {$definition}");
}
foreach ($pdo->query("SELECT id,link FROM notifications WHERE project_id IS NULL AND link LIKE 'projects/%'")->fetchAll() as $row) {
 $id=Notification::projectFromLink($row['link']);
 if ($id!==null) {$q=$pdo->prepare('UPDATE notifications SET project_id=? WHERE id=?');$q->execute([$id,$row['id']]);}
}
$pdo->exec("INSERT INTO permissions(name,code,description) VALUES ('Gestionar clases','classes.manage','Administrar contexto de clases y participantes autorizados; no matricula oficial.') ON CONFLICT (code) DO NOTHING");
$pdo->exec("INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='admin' AND p.code='classes.manage' ON CONFLICT DO NOTHING");
echo "Contexto de clases instalado.\n";
