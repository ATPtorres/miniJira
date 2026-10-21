<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
header('Cache-Control: no-store');
if (!is_logged_in() || !can('user.access')) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Sesion finalizada']);
    exit;
}
$userId = current_user_id();
$mode = (string) ($_GET['mode'] ?? 'feed');
if (is_post()) {
    header('Content-Type: application/json; charset=utf-8');
    if (!csrf_verify()) { http_response_code(419); echo json_encode(['ok'=>false]); exit; }
    $action = post('action');
    if ($action === 'mark-read') Notification::markRead((int) post('notif_id'), $userId);
    elseif ($action === 'mark-all-read') Notification::markAllRead($userId);
    elseif ($action === 'delete') Notification::delete((int) post('notif_id'), $userId);
    else { http_response_code(400); echo json_encode(['ok'=>false]); exit; }
    echo json_encode(['ok'=>true,'feed'=>Notification::feed($userId)]);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); exit; }
if ($mode === 'list') {
    header('Content-Type: text/html; charset=utf-8');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $typeFilter = is_string($_GET['type'] ?? null) ? $_GET['type'] : null;
    $readFilter = is_string($_GET['read'] ?? null) ? $_GET['read'] : null;
    $result = Notification::paginated($userId, $page, 15, $typeFilter, $readFilter);
    require APP_PATH . '/app/views/partials/notification-list.php';
    exit;
}
if ($mode !== 'stream') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(Notification::feed($userId));
    exit;
}

// Release the session lock so a stream never blocks logout or form submissions.
session_write_close();
// Configure reopening before emitting headers: PHP cannot change cookie/cache
// settings after the stream has started.
ini_set('session.use_cookies', '0');
session_cache_limiter('');
header('Content-Type: text/event-stream; charset=utf-8');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) ob_end_flush();
echo "retry: 3000\n\n";
flush();
$until = microtime(true) + 20;
$version = '';
do {
    // Recheck the session and active user on every tick, including after logout.
    $_SESSION = [];
    session_start(['read_and_close'=>true]);
    $user = User::findById($userId);
    if ((int) ($_SESSION['user_id'] ?? 0) !== $userId || !$user || !(int) $user['is_active']) {
        echo "event: expired\ndata: {}\n\n";
        flush();
        break;
    }
    $feed = Notification::feed($userId);
    if ($feed['version'] !== $version) {
        $version = $feed['version'];
        echo 'event: notifications' . "\n" . 'data: ' . json_encode($feed, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n";
    } else echo ": heartbeat\n\n";
    flush();
    if (connection_aborted()) break;
    sleep(2);
} while (microtime(true) < $until);
