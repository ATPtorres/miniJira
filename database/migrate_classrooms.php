<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/bootstrap.php';
db()->exec(file_get_contents(__DIR__ . '/migrations/20261022_classrooms.sql'));
$auditType=db()->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='classroom_audit' AND COLUMN_NAME='details'")->fetchColumn();
if($auditType!=='mediumtext')db()->exec('ALTER TABLE classroom_audit MODIFY details MEDIUMTEXT NOT NULL');
$columns = [
    ['projects', 'classroom_id', 'INT UNSIGNED NULL'],
    ['notifications', 'project_id', 'INT UNSIGNED NULL'],
];
foreach ($columns as [$table,$column,$type]) {
    $q=db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table,$column]);
    if (!(int)$q->fetchColumn()) db()->exec("ALTER TABLE $table ADD COLUMN $column $type");
}
foreach ([
    ['projects','fk_projects_class','FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE RESTRICT'],
    ['notifications','fk_notif_project','FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE'],
] as [$table,$name,$definition]) {
    $q=db()->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
    $q->execute([$table,$name]);
    if (!(int)$q->fetchColumn()) db()->exec("ALTER TABLE $table ADD CONSTRAINT $name $definition");
}
// Vincular avisos antiguos por su enlace; sin adivinar la clase de proyectos existentes.
foreach (db()->query('SELECT id,link FROM notifications WHERE project_id IS NULL AND link LIKE "projects/%"')->fetchAll() as $row) {
    $id=Notification::projectFromLink($row['link']);
    if ($id !== null) {
        $q=db()->prepare('UPDATE notifications SET project_id=? WHERE id=?'); $q->execute([$id,$row['id']]);
    }
}
db()->exec("INSERT IGNORE INTO permissions(name,code,description) VALUES ('Gestionar clases','classes.manage','Administrar contexto de clases y participantes autorizados; no matricula oficial.')");
db()->exec("INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='admin' AND p.code='classes.manage'");
echo "Contexto de clases instalado. Proyectos sin clase: solo supervision administrativa.\n";
