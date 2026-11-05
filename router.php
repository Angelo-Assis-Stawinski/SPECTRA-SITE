<?php
/* Use com php -S: .htaccess não é interpretado pelo servidor de desenvolvimento. */
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
if (preg_match('~(?:^|/)(?:private|data|bridge|tests|\.[^/]+)(?:/|$)~i',$path)
    || preg_match('~(?:^|/)(?:config|bootstrap|router)\.php$~i',$path)
    || strpos($path,'..')!==false || strpos($path,'\\')!==false) {
    http_response_code(403); exit('Acesso negado');
}
if (preg_match('~^/(uploads|public_data)/([A-Za-z0-9][A-Za-z0-9._-]*)$~',$path,$match)) {
    $_GET['type']=$match[1]==='uploads' ? 'attachment' : 'data'; $_GET['f']=$match[2];
    require __DIR__.'/download.php'; return true;
}
if (preg_match('~^/(uploads|public_data)(?:/|$)~i',$path)) { http_response_code(403); exit('Acesso negado'); }
header('X-Content-Type-Options: nosniff');
return false;
