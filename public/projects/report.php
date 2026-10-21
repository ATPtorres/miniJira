<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';
requireAuth();
$projectId=(int)($_GET['id'] ?? 0);
if ($projectId<=0 || !Project::userCanAccess($projectId,current_user_id(),can('projects.manage.all'))) access_denied();
session_write_close();
$contents=ProjectReport::render($projectId);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="proyecto-'.$projectId.'-'.date('Y-m-d').'.pdf"');
header('Content-Length: '.strlen($contents));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $contents;
