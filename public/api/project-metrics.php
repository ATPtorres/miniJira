<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_logged_in()) { http_response_code(401); echo json_encode(['error'=>'Sesion finalizada']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); exit; }
if(isset($_GET['batch'])){
    try {
        $raw=$_GET['ids']??'';
        if(!is_string($raw) || !preg_match('/^[0-9]+(?:,[0-9]+){0,24}$/',$raw))throw new InvalidArgumentException('Lista no válida.');
        echo json_encode(['projects'=>Project::progressBatch(explode(',',$raw),current_user_id(),can('projects.manage.all'))]);
    }catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);}
    exit;
}
if (isset($_GET['summary'])) {
    $metrics = ProjectMetrics::fromCounts(Project::summaryFor(current_user_id(),can('projects.manage.all')));
    echo json_encode(['metrics'=>$metrics,'states_html'=>ProjectCharts::states($metrics)]);
    exit;
}
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !Project::userCanAccess($id,current_user_id(),can('projects.manage.all'))) {
    http_response_code(403); echo json_encode(['error'=>'Acceso denegado']); exit;
}
try { [$from,$to] = ProjectMetrics::dateRange($_GET['from'] ?? null,$_GET['to'] ?? null); }
catch (InvalidArgumentException $e) { http_response_code(400); echo json_encode(['error'=>$e->getMessage()]); exit; }
session_write_close();
$project = Project::find($id);
$metrics = ProjectMetrics::forProject($id);
echo json_encode([
    'metrics'=>$metrics,'timeline'=>ProjectMetrics::timeline($project),'students'=>ProjectMetrics::students($id),
    'states_html'=>ProjectCharts::states($metrics),
    'history_html'=>ProjectCharts::history(ProjectMetrics::history($id,$from,$to)),
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
