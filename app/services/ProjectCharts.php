<?php
declare(strict_types=1);

/** Graficos sin CDN, con representacion textual accesible. */
final class ProjectCharts
{
    public static function states(array $metrics): string
    {
        $colors = ['todo'=>'#65748b','doing'=>'#2874a6','review'=>'#ad7900','done'=>'#22764d'];
        $html = '<div class="chart-bars" aria-label="Distribucion de tareas">';
        foreach (ProjectMetrics::LABELS as $status => $label) {
            $value = (int) $metrics[$status];
            $width = ProjectMetrics::percentage($value, (int) $metrics['total']);
            $html .= '<div class="chart-row"><span>'.e($label).'</span><span class="chart-track"><span style="width:'.$width.'%;background:'.$colors[$status].'"></span></span><strong>'.$value.'</strong></div>';
        }
        return $html.'</div>';
    }

    public static function history(array $history): string
    {
        if (!$history) return '<p class="text-muted">Aun no hay observaciones en este periodo.</p>';
        $points = [];
        $first = strtotime($history[0]['date']);
        $span = max(1, strtotime($history[count($history)-1]['date']) - $first);
        foreach ($history as $item) {
            $points[] = (30+(strtotime($item['date'])-$first)/$span*540).','.(130-(int)$item['progress']);
        }
        $html = '<svg class="history-chart" viewBox="0 0 600 165" role="img" aria-label="Avance observado por dia"><text x="0" y="32">100%</text><text x="8" y="133">0%</text><path d="M30 30H580M30 80H580M30 130H580" stroke="#ddd" fill="none"/><polyline points="'.implode(' ',$points).'" fill="none" stroke="#2874a6" stroke-width="3"/>';
        foreach ($points as $i=>$point) {
            [$x,$y]=explode(',',$point);
            $html.='<circle cx="'.$x.'" cy="'.$y.'" r="4" fill="#2874a6"><title>'.e($history[$i]['date']).': '.(int)$history[$i]['progress'].'%</title></circle>';
        }
        $html.='<text x="30" y="155">'.e($history[0]['date']).'</text><text x="570" y="155" text-anchor="end">'.e($history[count($history)-1]['date']).'</text></svg><details><summary>Ver datos del grafico</summary><table><thead><tr><th>Fecha</th><th>Avance</th></tr></thead><tbody>';
        foreach ($history as $item) $html.='<tr><td>'.e($item['date']).'</td><td>'.(int)$item['progress'].'%</td></tr>';
        return $html.'</tbody></table></details><p class="text-muted">Ultima observacion de cada dia registrado. El historial comienza al instalar Beta.</p>';
    }
}
