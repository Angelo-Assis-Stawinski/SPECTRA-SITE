<?php
/* Banco e estado privados. A base SQLite existente é preservada. */
function json_out($value, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
set_exception_handler(function ($error) {
    error_log('SPECTRA: '.$error->getMessage());
    json_out(['ok'=>false, 'error'=>'Erro no servidor. Consulte diagnostico.php e o log do PHP.'], 500);
});
$config = require __DIR__.'/config.php';
foreach (['private', 'data', 'public_data', 'uploads'] as $dir) {
    $path = __DIR__.'/'.$dir;
    if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
        throw new RuntimeException('Não foi possível criar '.$dir);
    }
}
if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    json_out(['ok'=>false, 'error'=>'PDO_SQLite ausente. Habilite extension=pdo_sqlite no php.ini e reinicie o PHP.'], 503);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax',
        'secure'=>isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    if (ini_get('session.save_handler') === 'files') {
        $session_dir = ini_get('session.save_path') ?: sys_get_temp_dir();
        if (!is_dir($session_dir) || !is_writable($session_dir)) {
            $session_dir = __DIR__.'/private/sessions';
            if (!is_dir($session_dir) && !mkdir($session_dir,0750,true) && !is_dir($session_dir)) {
                throw new RuntimeException('Falha ao criar pasta de sessões');
            }
            session_save_path($session_dir);
        }
    }
    if (!session_start()) { throw new RuntimeException('Falha ao iniciar sessão PHP'); }
}
$pdo = new PDO('sqlite:'.$config['db_path']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA busy_timeout=3000; PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
$pdo->exec("CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,email TEXT UNIQUE NOT NULL,password_hash TEXT NOT NULL,role TEXT NOT NULL DEFAULT 'user',created_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS comments(id INTEGER PRIMARY KEY,user_id INTEGER NOT NULL,category TEXT NOT NULL,body TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',created_at TEXT NOT NULL,updated_at TEXT NOT NULL,FOREIGN KEY(user_id) REFERENCES users(id))");
$pdo->exec("CREATE TABLE IF NOT EXISTS field_logs(id INTEGER PRIMARY KEY,title TEXT NOT NULL,body TEXT NOT NULL,source TEXT NOT NULL DEFAULT 'manual',source_key TEXT UNIQUE,attachment_url TEXT,created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE IF NOT EXISTS messages(id INTEGER PRIMARY KEY,title TEXT NOT NULL,body TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
$st = $pdo->prepare('SELECT id FROM users WHERE lower(email)=lower(?)');
$st->execute([$config['admin_email']]);
if (!$st->fetchColumn()) {
    $st = $pdo->prepare('INSERT OR IGNORE INTO users(email,password_hash,role,created_at) VALUES(?,?,?,?)');
    $st->execute([$config['admin_email'], $config['admin_password_hash'], 'admin', gmdate('c')]);
}
if (!is_file($config['bridge_token_path'])) {
    $handle = @fopen($config['bridge_token_path'], 'x');
    if ($handle) { fwrite($handle, bin2hex(random_bytes(24))); fclose($handle); }
    elseif (!is_file($config['bridge_token_path'])) { throw new RuntimeException('Falha ao criar token'); }
}
/* Migração da telemetria antiga para uma área que não deve ser servida. */
$legacy = __DIR__.'/data/telemetry.json';
if (is_file($legacy)) {
    if (!is_file($config['telemetry_path']) && !rename($legacy, $config['telemetry_path'])) {
        throw new RuntimeException('Falha na migração da telemetria');
    }
    if (is_file($legacy) && !unlink($legacy)) { throw new RuntimeException('Falha ao remover estado legado'); }
}
function body_json() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) { json_out(['ok'=>false, 'error'=>'JSON inválido.'], 400); }
    return $data;
}
function user_now() {
    global $pdo;
    if (empty($_SESSION['uid'])) { return null; }
    $st = $pdo->prepare('SELECT id,email,role FROM users WHERE id=?');
    $st->execute([$_SESSION['uid']]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
function need_user() {
    $user = user_now();
    if (!$user) { json_out(['ok'=>false, 'error'=>'Faça login para continuar.'], 401); }
    return $user;
}
function need_admin() {
    $user = need_user();
    if ($user['role'] !== 'admin') { json_out(['ok'=>false, 'error'=>'Acesso administrativo necessário.'], 403); }
    return $user;
}
function csrf() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(20)); }
    return $_SESSION['csrf'];
}
function check_csrf() {
    if (!hash_equals(csrf(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        json_out(['ok'=>false, 'error'=>'Sessão expirada ou CSRF inválido. Recarregue o painel.'], 403);
    }
}
function bridge_ok() {
    global $config;
    $real = trim((string)@file_get_contents($config['bridge_token_path']));
    return $real !== '' && hash_equals($real, $_SERVER['HTTP_X_TELEMETRY_TOKEN'] ?? '');
}
function text_length($text) {
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : preg_match_all('/./us', $text);
}
function ini_bytes($value) {
    $value = trim($value); $unit = strtolower(substr($value, -1));
    return (int)((float)$value * (['g'=>1073741824, 'm'=>1048576, 'k'=>1024][$unit] ?? 1));
}
function upload_limit() {
    global $config;
    $limits = [(int)$config['max_upload_bytes']];
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $value = ini_bytes(ini_get($key)); if ($value > 0) { $limits[] = $value; }
    }
    return min($limits);
}
function uploaded_file($allow, $directory, $random_name = true) {
    global $config;
    $post_limit = ini_bytes(ini_get('post_max_size'));
    if ($post_limit > 0 && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $post_limit) {
        json_out(['ok'=>false, 'error'=>'Upload excedeu post_max_size do PHP. Ajuste o php.ini e reinicie o servidor.'], 413);
    }
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || is_array($file['error'] ?? null)) {
        json_out(['ok'=>false, 'error'=>'Selecione um arquivo para anexar.'], 422);
    }
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code !== UPLOAD_ERR_OK) {
        $errors = [1=>'Arquivo excedeu upload_max_filesize do PHP.', 2=>'Arquivo excedeu o limite do formulário.',
            3=>'Upload incompleto. Tente novamente.', 4=>'Nenhum arquivo selecionado.',
            6=>'Pasta temporária do PHP ausente.', 7=>'PHP não conseguiu gravar o arquivo temporário.',
            8=>'Upload bloqueado por uma extensão do PHP.'];
        json_out(['ok'=>false, 'error'=>$errors[$code] ?? 'Falha no upload.'], in_array($code, [1,2], true) ? 413 : 422);
    }
    if ($file['size'] > $config['max_upload_bytes']) {
        json_out(['ok'=>false, 'error'=>'Arquivo excedeu o limite de 25 MiB do site.'], 413);
    }
    $original = basename(str_replace('\\', '/', $file['name']));
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, $allow, true)) {
        json_out(['ok'=>false, 'error'=>'Tipo não permitido. Formatos: '.implode(', ', $allow).'.'], 422);
    }
    $name = $random_name ? bin2hex(random_bytes(16)).'.'.$extension : preg_replace('/[^A-Za-z0-9._-]+/', '_', $original);
    if (!is_writable($directory)) {
        json_out(['ok'=>false, 'error'=>'Pasta de destino sem permissão de gravação. Verifique diagnostico.php.'], 500);
    }
    $path = $directory.'/'.$name;
    if (!@move_uploaded_file($file['tmp_name'], $path) || !is_file($path)) {
        json_out(['ok'=>false, 'error'=>'Não foi possível salvar o anexo. Confira permissões e espaço em disco.'], 500);
    }
    return $name;
}
function validate_attachment($url) {
    if ($url === '') { return null; }
    if (preg_match('~^uploads/([a-f0-9]{16,32}\.[a-z0-9]+)$~', $url, $match)) { $name = $match[1]; }
    elseif (preg_match('~^download\.php\?type=attachment&f=([a-f0-9]{16,32}\.[a-z0-9]+)$~', $url, $match)) { $name = $match[1]; }
    else { json_out(['ok'=>false, 'error'=>'Referência de anexo inválida. Envie o arquivo pelo painel.'], 422); }
    if (!is_file(__DIR__.'/uploads/'.$name)) { json_out(['ok'=>false, 'error'=>'Anexo não encontrado no servidor. Envie novamente.'], 422); }
    return 'download.php?type=attachment&f='.$name;
}
