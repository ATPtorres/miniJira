<?php
/** PostgreSQL-compatible, additive file-version migration. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$pdo = db();
$columns = [
    'version_root_id' => 'INTEGER NULL',
    'version_number' => 'INTEGER NOT NULL DEFAULT 1',
    'deleted_by_id' => 'INTEGER NULL',
];
foreach ($columns as $name => $definition) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='project_files' AND column_name=?");
    $q->execute([$name]);
    if (!(int)$q->fetchColumn()) $pdo->exec("ALTER TABLE project_files ADD COLUMN {$name} {$definition}");
}
$q = $pdo->prepare("SELECT COUNT(*) FROM pg_indexes WHERE schemaname=current_schema() AND tablename='project_files' AND indexname='uq_pf_version'");
$q->execute();
if (!(int)$q->fetchColumn()) $pdo->exec('CREATE UNIQUE INDEX uq_pf_version ON project_files(version_root_id, version_number)');
foreach ([
    'fk_pf_version_root' => 'FOREIGN KEY (version_root_id) REFERENCES project_files (id) ON DELETE CASCADE',
    'fk_pf_deleted_by' => 'FOREIGN KEY (deleted_by_id) REFERENCES users (id) ON DELETE SET NULL',
] as $name => $definition) {
    $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=current_schema() AND table_name='project_files' AND constraint_name=?");
    $q->execute([$name]);
    if (!(int)$q->fetchColumn()) $pdo->exec("ALTER TABLE project_files ADD CONSTRAINT {$name} {$definition}");
}
echo "Migracion de historial de archivos aplicada.\n";
