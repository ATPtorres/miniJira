<?php
/** Ruta antigua: sin modificaciones de expedientes o notas oficiales. */
declare(strict_types=1);
require_once __DIR__.'/../../app/bootstrap.php';requireAuth();
if(is_post()){http_response_code(410);exit('La gestión de expedientes oficiales no está disponible en MiniJira.');}
redirect('user/classes.php');
