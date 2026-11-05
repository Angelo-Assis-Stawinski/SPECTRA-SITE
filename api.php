<?php
require __DIR__.'/bootstrap.php';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
/* Todas as operações que alteram dados exigem POST. */
$writes = ['register','login','logout','comment_create','admin_log_save','admin_log_delete',
    'admin_comment_status','admin_message_save','admin_message_delete','admin_upload',
    'admin_change_password','bridge_upload','bridge_file'];
if (in_array($action, $writes, true) && $method !== 'POST') {
    header('Allow: POST'); json_out(['ok'=>false,'error'=>'Use POST para esta ação.'],405);
}
switch ($action) {
case 'me':
    $user = user_now();
    json_out(['ok'=>true,'user'=>$user,'csrf'=>$user ? csrf() : null]);
case 'register':
    $body=body_json(); $email=strtolower(trim((string)($body['email'] ?? ''))); $password=(string)($body['password'] ?? '');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>254 || strlen($password)<8 || strlen($password)>72) {
        json_out(['ok'=>false,'error'=>'Use um e-mail válido e senha de 8 a 72 bytes.'],422);
    }
    $st=$pdo->prepare('SELECT id FROM users WHERE lower(email)=lower(?)'); $st->execute([$email]);
    if ($st->fetchColumn()) { json_out(['ok'=>false,'error'=>'Esse e-mail já está cadastrado.'],409); }
    $st=$pdo->prepare('INSERT INTO users(email,password_hash,role,created_at) VALUES(?,?,?,?)');
    $st->execute([$email,password_hash($password,PASSWORD_DEFAULT),'user',gmdate('c')]);
    session_regenerate_id(true); $_SESSION['uid']=$pdo->lastInsertId(); unset($_SESSION['csrf']);
    json_out(['ok'=>true,'csrf'=>csrf()]);
case 'login':
    $body=body_json(); $st=$pdo->prepare('SELECT * FROM users WHERE lower(email)=lower(?)');
    $st->execute([trim((string)($body['email'] ?? ''))]); $user=$st->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify((string)($body['password'] ?? ''),$user['password_hash'])) {
        json_out(['ok'=>false,'error'=>'E-mail ou senha incorretos.'],401);
    }
    session_regenerate_id(true); $_SESSION['uid']=$user['id']; unset($_SESSION['csrf']);
    json_out(['ok'=>true,'user'=>['id'=>$user['id'],'email'=>$user['email'],'role'=>$user['role']],'csrf'=>csrf()]);
case 'logout':
    check_csrf(); $_SESSION=[]; session_destroy(); json_out(['ok'=>true]);
case 'telemetry':
case 'admin_telemetry':
    if ($action==='admin_telemetry') { need_admin(); }
    $path=$config['telemetry_path']; $data=is_file($path) ? json_decode(file_get_contents($path),true) : null;
    if (is_array($data)) {
        if ($action==='admin_telemetry' && isset($data['config_private'])) { $data['config']=$data['config_private']; }
        unset($data['config_private']);
    }
    json_out(['ok'=>true,'telemetry'=>$data]);
case 'comments':
    $st=$pdo->query("SELECT c.id,c.category,c.body,c.created_at,u.email FROM comments c JOIN users u ON u.id=c.user_id WHERE c.status='published' ORDER BY c.id DESC LIMIT 100");
    $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        [$name,$domain]=array_pad(explode('@',$row['email'],2),2,'');
        $row['email_masked']=substr($name,0,2).'***@'.$domain; unset($row['email']);
    }
    json_out(['ok'=>true,'comments'=>$rows]);
case 'comment_create':
    $user=need_user(); check_csrf(); $body=body_json(); $category=(string)($body['category'] ?? 'Dúvidas');
    if (!in_array($category,['Dúvidas','Comentário','Sugestão'],true)) { $category='Dúvidas'; }
    $text=trim((string)($body['body'] ?? ''));
    if ($text==='' || text_length($text)>3000) { json_out(['ok'=>false,'error'=>'Comentário vazio ou com mais de 3000 caracteres.'],422); }
    $st=$pdo->prepare('INSERT INTO comments(user_id,category,body,status,created_at,updated_at) VALUES(?,?,?,?,?,?)');
    $now=gmdate('c'); $st->execute([$user['id'],$category,$text,'pending',$now,$now]); json_out(['ok'=>true]);
case 'logs':
    $st=$pdo->query('SELECT id,title,body,source,attachment_url,created_at,updated_at FROM field_logs ORDER BY id DESC LIMIT 100');
    json_out(['ok'=>true,'logs'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
case 'messages':
    $st=$pdo->query('SELECT id,title,body,created_at FROM messages WHERE active=1 ORDER BY id DESC LIMIT 5');
    json_out(['ok'=>true,'messages'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
case 'admin_logs':
    need_admin(); $st=$pdo->query('SELECT * FROM field_logs ORDER BY id DESC LIMIT 200');
    json_out(['ok'=>true,'logs'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
case 'admin_comments':
    need_admin(); $st=$pdo->query('SELECT c.*,u.email FROM comments c JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT 200');
    json_out(['ok'=>true,'comments'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
case 'admin_messages':
    need_admin(); $st=$pdo->query('SELECT * FROM messages ORDER BY id DESC LIMIT 100');
    json_out(['ok'=>true,'messages'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
case 'admin_upload_limits':
    need_admin(); json_out(['ok'=>true,'max_bytes'=>upload_limit(),'php_upload_max'=>ini_get('upload_max_filesize'),
        'php_post_max'=>ini_get('post_max_size'),'site_max_bytes'=>$config['max_upload_bytes']]);
case 'admin_log_save':
case 'admin_message_save':
    need_admin(); check_csrf(); $body=body_json(); $id=(int)($body['id'] ?? 0);
    $title=trim((string)($body['title'] ?? '')); $text=trim((string)($body['body'] ?? ''));
    if ($title==='' || $text==='' || text_length($title)>180 || text_length($text)>20000) {
        json_out(['ok'=>false,'error'=>'Título e texto obrigatórios. Limites: 180 e 20000 caracteres.'],422);
    }
    $table=$action==='admin_log_save' ? 'field_logs' : 'messages'; $now=gmdate('c');
    if ($id) {
        $st=$pdo->prepare('SELECT id FROM '.$table.' WHERE id=?'); $st->execute([$id]);
        if (!$st->fetchColumn()) { json_out(['ok'=>false,'error'=>'Registro não encontrado.'],404); }
    }
    if ($table==='field_logs') {
        $attachment=validate_attachment(trim((string)($body['attachment_url'] ?? '')));
        if ($id) {
            $st=$pdo->prepare('UPDATE field_logs SET title=?,body=?,attachment_url=?,updated_at=? WHERE id=?');
            $st->execute([$title,$text,$attachment,$now,$id]);
        } else {
            $st=$pdo->prepare('INSERT INTO field_logs(title,body,source,attachment_url,created_at,updated_at) VALUES(?,?,?,?,?,?)');
            $st->execute([$title,$text,'manual',$attachment,$now,$now]); $id=(int)$pdo->lastInsertId();
        }
    } else {
        $active=!empty($body['active']) ? 1 : 0;
        if ($id) {
            $st=$pdo->prepare('UPDATE messages SET title=?,body=?,active=?,updated_at=? WHERE id=?');
            $st->execute([$title,$text,$active,$now,$id]);
        } else {
            $st=$pdo->prepare('INSERT INTO messages(title,body,active,created_at,updated_at) VALUES(?,?,?,?,?)');
            $st->execute([$title,$text,$active,$now,$now]); $id=(int)$pdo->lastInsertId();
        }
    }
    json_out(['ok'=>true,'id'=>$id]);
case 'admin_log_delete':
case 'admin_message_delete':
    need_admin(); check_csrf(); $body=body_json(); $id=(int)($body['id'] ?? 0);
    $table=$action==='admin_log_delete' ? 'field_logs' : 'messages';
    $st=$pdo->prepare('DELETE FROM '.$table.' WHERE id=?'); $st->execute([$id]);
    if (!$st->rowCount()) { json_out(['ok'=>false,'error'=>'Registro não encontrado.'],404); }
    json_out(['ok'=>true]);
case 'admin_comment_status':
    need_admin(); check_csrf(); $body=body_json(); $status=$body['status'] ?? '';
    if (!in_array($status,['published','hidden','pending'],true)) { json_out(['ok'=>false,'error'=>'Estado inválido.'],422); }
    $st=$pdo->prepare('UPDATE comments SET status=?,updated_at=? WHERE id=?');
    $st->execute([$status,gmdate('c'),(int)($body['id'] ?? 0)]); json_out(['ok'=>true]);
case 'admin_upload':
    need_admin(); check_csrf();
    $name=uploaded_file(['txt','c','csv','json','pdf','png','jpg','jpeg','webp','bmp','wav','zip','fits','fit'],__DIR__.'/uploads');
    json_out(['ok'=>true,'url'=>'download.php?type=attachment&f='.$name]);
case 'admin_bridge_token':
    need_admin(); json_out(['ok'=>true,'token'=>trim(file_get_contents($config['bridge_token_path']))]);
case 'admin_change_password':
    $user=need_admin(); check_csrf(); $body=body_json(); $password=(string)($body['password'] ?? '');
    if (strlen($password)<10 || strlen($password)>72) { json_out(['ok'=>false,'error'=>'Use senha de 10 a 72 bytes.'],422); }
    $st=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
    $st->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]); json_out(['ok'=>true]);
case 'bridge_upload':
    if (!bridge_ok()) { json_out(['ok'=>false,'error'=>'Token da ponte inválido.'],403); }
    $raw=file_get_contents('php://input',false,null,0,6*1024*1024+1);
    if (strlen($raw)>6*1024*1024) { json_out(['ok'=>false,'error'=>'Payload grande demais.'],413); }
    $data=json_decode($raw,true);
    if (!is_array($data) || ($data['schema'] ?? 0)!==1) { json_out(['ok'=>false,'error'=>'Payload inválido.'],422); }
    $tmp=tempnam(dirname($config['telemetry_path']),'tel-');
    if ($tmp===false || file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false || !rename($tmp,$config['telemetry_path'])) {
        if ($tmp && is_file($tmp)) { unlink($tmp); }
        json_out(['ok'=>false,'error'=>'Não foi possível gravar a telemetria.'],500);
    }
    if (!empty($data['auto_log']) && is_array($data['auto_log'])) {
        $log=$data['auto_log']; $key='auto:'.($log['key'] ?? ($data['sync_id'] ?? sha1($raw)));
        $st=$pdo->prepare('INSERT OR IGNORE INTO field_logs(title,body,source,source_key,created_at,updated_at) VALUES(?,?,?,?,?,?)');
        $now=gmdate('c'); $st->execute([substr((string)($log['title'] ?? 'Sincronização automática'),0,180),
            substr((string)($log['body'] ?? ''),0,20000),'automatico',$key,$now,$now]);
    }
    json_out(['ok'=>true]);
case 'bridge_file':
    if (!bridge_ok()) { json_out(['ok'=>false,'error'=>'Token da ponte inválido.'],403); }
    $name=uploaded_file(['csv','txt','wav','bmp','fits','fit','json'],__DIR__.'/public_data',false);
    json_out(['ok'=>true,'public_name'=>$name]);
default:
    json_out(['ok'=>false,'error'=>'Ação desconhecida.'],404);
}
