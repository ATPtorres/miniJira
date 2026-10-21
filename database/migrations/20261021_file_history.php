<?php
/** Migración aditiva y repetible para ULAT-21; nunca reinicia datos o permisos. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$columns = [
    'version_root_id' => 'INT UNSIGNED NULL',
    'version_number' => 'INT UNSIGNED NOT NULL DEFAULT 1',
    'deleted_by_id' => 'INT UNSIGNED NULL',
];
foreach ($columns as $name => $definition) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="project_files" AND COLUMN_NAME=?');
    $stmt->execute([$name]);
    if (!(int) $stmt->fetchColumn()) db()->exec('ALTER TABLE project_files ADD COLUMN ' . $name . ' ' . $definition);
}
$stmt = db()->query('SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="project_files" AND INDEX_NAME="uq_pf_version"');
if (!(int) $stmt->fetchColumn()) {
    db()->exec('ALTER TABLE project_files ADD UNIQUE KEY uq_pf_version (version_root_id, version_number)');
}
foreach ([
    'fk_pf_version_root' => 'FOREIGN KEY (version_root_id) REFERENCES project_files (id) ON DELETE CASCADE',
    'fk_pf_deleted_by' => 'FOREIGN KEY (deleted_by_id) REFERENCES users (id) ON DELETE SET NULL',
] as $name => $definition) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME="project_files" AND CONSTRAINT_NAME=?');
    $stmt->execute([$name]);
    if (!(int) $stmt->fetchColumn()) db()->exec('ALTER TABLE project_files ADD CONSTRAINT ' . $name . ' ' . $definition);
}
