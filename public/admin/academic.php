<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';requireAuth();
if(is_post()){http_response_code(410);exit('La gestión de inscripciones y notas oficiales pertenece a la plataforma universitaria.');}
requirePermission('classes.manage');redirect('admin/classes.php');
