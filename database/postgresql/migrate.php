<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../app/bootstrap.php';
$schema = file_get_contents(__DIR__ . '/install.sql');
if ($schema === false) throw new RuntimeException('No se pudo leer el esquema PostgreSQL.');
$pdo=db();
if ($pdo instanceof MiniJiraPostgresPDO) $pdo->rawExec($schema); else $pdo->exec($schema);
echo "Esquema PostgreSQL instalado correctamente.\n";
