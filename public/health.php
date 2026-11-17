<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
try {
    require_once __DIR__ . '/../app/bootstrap.php';
    db()->query('SELECT 1');
    http_response_code(200);
    echo json_encode(['status'=>'ok','service'=>'MiniJira','database'=>'connected'],JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['status'=>'error','service'=>'MiniJira','database'=>'unavailable'],JSON_UNESCAPED_SLASHES);
}
