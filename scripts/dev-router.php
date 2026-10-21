<?php
// Servidor local: el checkout nunca es document root.
$root=dirname(__DIR__).'/public';
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
$file=realpath($root.$path);
header('X-Content-Type-Options: nosniff');
if (preg_match('~(^|/)\.~',$path) || !$file || ($file!==$root && !str_starts_with($file,$root.'/'))) {
    http_response_code(404);echo 'Not Found';return true;
}
return false;
