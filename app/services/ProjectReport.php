<?php
declare(strict_types=1);

final class ProjectReport
{
    public static function render(int $projectId): string
    {
        $autoload=APP_PATH.'/vendor/autoload.php';
        if (!is_file($autoload)) throw new RuntimeException('Ejecuta composer install para habilitar PDF');
        require_once $autoload;
        $project=Project::find($projectId);
        if (!$project || $project['deleted_at'] !== null) throw new RuntimeException('Proyecto no disponible');
        $metrics=ProjectMetrics::forProject($projectId);
        $students=ProjectMetrics::students($projectId);
        $tasks=Task::forProject($projectId);
        if(!Project::userCanManage($projectId,current_user_id(),can('projects.manage.all'))) {
            $students=array_values(array_filter($students,fn($s)=>(int)$s['id']===current_user_id()));
            $tasks=array_values(array_filter($tasks,fn($t)=>(int)$t['assigned_student_id']===current_user_id()));
        }
        ob_start();
        require APP_PATH.'/app/views/reports/project.php';
        $html=ob_get_clean();
        $options=new Dompdf\Options();
        $options->set('isRemoteEnabled',false);
        $options->set('isPhpEnabled',false);
        $options->set('isJavascriptEnabled',false);
        $options->set('chroot',APP_PATH.'/public/assets');
        $options->set('defaultFont','DejaVu Sans');
        $options->set('tempDir',sys_get_temp_dir());
        $pdf=new Dompdf\Dompdf($options);
        $pdf->loadHtml($html,'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();
        return $pdf->output();
    }
}
