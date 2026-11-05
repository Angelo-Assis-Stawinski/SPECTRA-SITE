<?php
header('Content-Type: text/plain; charset=utf-8');
echo "SPECTRA Site - diagnostico\nPHP: ".PHP_VERSION."\n";
echo 'PDO_SQLite: '.((class_exists('PDO') && in_array('sqlite',PDO::getAvailableDrivers(),true)) ? 'OK' : 'AUSENTE')."\n";
echo 'JSON: '.(function_exists('json_encode') ? 'OK' : 'AUSENTE')."\n";
echo 'mbstring: '.(function_exists('mb_strlen') ? 'OK' : 'opcional; fallback UTF-8 ativo')."\n";
echo 'file_uploads: '.(ini_get('file_uploads') ? 'OK' : 'DESATIVADO')."\n";
foreach (['upload_max_filesize','post_max_size','upload_tmp_dir'] as $key) { echo $key.': '.(ini_get($key) ?: '(padrão do sistema)')."\n"; }
echo 'php.ini: '.(php_ini_loaded_file() ?: 'nenhum')."\n";
foreach (['private','data','public_data','uploads'] as $dir) {
    $path=__DIR__.'/'.$dir;
    if (!is_dir($path)) { @mkdir($path,0750,true); }
    echo $dir.': '.((is_dir($path) && is_writable($path)) ? 'gravavel' : 'verifique permissao')."\n";
}
echo "\nTeste local: use INICIAR_SITE.bat (uploads 25 MiB e router de proteção).\n";
