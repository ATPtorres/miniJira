<?php declare(strict_types=1); ?>
<section class="card live-analytics" data-live-project="<?= (int)$projectId ?>" data-from="<?= e($from) ?>" data-to="<?= e($to) ?>">
  <h2>Analitica del proyecto</h2>
  <p>Avance: <strong data-live-value="progress"><?= $metrics['progress'] ?></strong>% · <span data-live-value="done"><?= $metrics['done'] ?></span> de <span data-live-value="total"><?= $metrics['total'] ?></span> tareas terminadas</p>
  <div class="progress" role="progressbar" aria-label="Avance del proyecto" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $metrics['progress'] ?>"><div class="progress-bar" data-live-bar style="width:<?= $metrics['progress'] ?>%"></div></div>
  <h3>Tareas por estado</h3>
  <div data-live-states><?= ProjectCharts::states($metrics) ?></div>
  <h3>Evolucion del avance</h3>
  <div data-live-history><?= ProjectCharts::history(ProjectMetrics::history($projectId,$from,$to)) ?></div>
  <p><a class="btn btn-outline-dark" href="<?= e(base_url('projects/report.php?id='.$projectId)) ?>">Descargar informe PDF</a></p>
  <p class="text-muted" data-live-feedback role="status"></p>
</section>
