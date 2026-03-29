<?php
// accounts_list.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$stmt = $conn->prepare("SELECT id, account_name, type, balance FROM accounts WHERE user_id = ?");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
respond(true, 'accounts', $out);
