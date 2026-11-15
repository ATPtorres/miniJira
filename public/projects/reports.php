<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';
requireAuth();
try {$data=ConsolidatedReport::build($_GET);}
catch(InvalidArgumentException $e) {http_response_code(400);exit(e($e->getMessage()));}
header('Cache-Control: private, no-store');
if(($_GET['format']??'')==='pdf') {
    require_once APP_PATH.'/vendor/autoload.php';
    ob_start(); ?>
    <!doctype html><html lang="es"><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:9px}table{width:100%;border-collapse:collapse}td,th{padding:5px;border-bottom:1px solid #ddd;text-align:left}tr{page-break-inside:avoid}.report-cell-label{display:none}.report-context{display:block;font-weight:normal}</style><body>
    <?php require APP_PATH.'/app/views/reports/consolidated.php'; ?></body></html><?php
    $html=ob_get_clean();$options=new Dompdf\Options();
    $options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('isJavascriptEnabled',false);
    $options->set('chroot',APP_PATH.'/public/assets');
    $pdf=new Dompdf\Dompdf($options);$pdf->loadHtml($html,'UTF-8');$pdf->setPaper('A4','landscape');$pdf->render();
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="reporte-consolidado.pdf"');echo $pdf->output();exit;
}
$selection=$_GET;unset($selection['student_id']);
$studentOptions=$data['own']?[]:ConsolidatedReport::build($selection)['students'];
$pageTitle='Reportes consolidados';require APP_PATH.'/app/views/partials/header.php';
$options=Workload::options(current_user_id(),can('projects.manage.all'),$data['filters']);
?>
<details class="report-filter-panel" data-report-filters open>
<summary>Filtros del reporte</summary>
<form method="get" class="card analytics-filters report-filters">
<?php foreach(Workload::FIELDS as $key=>$label): ?><label><?= e($label) ?><select name="<?= e($key) ?>"><option value="">Todos</option><?php foreach($options[$key] as $value): ?><option value="<?= e((string)$value) ?>" <?= (string)$value===$data['filters'][$key]?'selected':'' ?>><?= e((string)$value) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
<label>Clase (ID)<input type="number" min="1" name="classroom_id" value="<?= e($data['filters']['classroom_id']) ?>"></label>
<?php if(!$data['own']): ?><label>Estudiante<select name="student_id"><option value="">Todos</option><?php foreach($studentOptions as $s): ?><option value="<?= $s['id'] ?>" <?= (string)$s['id']===(string)$data['student']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
<button class="btn btn-primary">Aplicar filtros</button>
</form></details>
<div class="report-actions"><a class="btn btn-outline-dark" href="?<?= e(http_build_query(array_merge($data['filters'], ['student_id'=>$data['student'], 'format'=>'pdf']))) ?>">Descargar PDF</a><span class="text-muted">Exporta los resultados mostrados.</span></div>
<section class="card report-content">
<?php require APP_PATH.'/app/views/reports/consolidated.php'; ?></section>
<?php require APP_PATH.'/app/views/partials/footer.php'; ?>
