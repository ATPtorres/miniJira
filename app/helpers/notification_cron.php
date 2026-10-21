<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../bootstrap.php';
session_write_close();
try {
    $count=ReminderService::run();
    echo '['.date(DATE_ATOM).'] Recordatorios creados: '.$count.PHP_EOL;
} catch (Throwable $e) {
    log_error($e->getMessage(),'reminders.log');
    fwrite(STDERR,'Fallo de recordatorios; revisar storage/logs/reminders.log'.PHP_EOL);
    exit(1);
}
