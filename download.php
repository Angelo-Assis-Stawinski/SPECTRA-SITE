<?php
require __DIR__.'/bootstrap.php';
$name=basename((string)($_GET['f'] ?? ''));
$type=$_GET['type'] ?? 'data';
if ($name==='' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/',$name) || !in_array($type,['data','attachment'],true)) {
    http_response_code(400); exit('Arquivo inválido');
}
$path=__DIR__.($type==='attachment' ? '/uploads/' : '/public_data/').$name;
if (!is_file($path) || is_link($path)) { http_response_code(404); exit('Arquivo não encontrado'); }
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.filesize($path));
session_write_close(); readfile($path);
