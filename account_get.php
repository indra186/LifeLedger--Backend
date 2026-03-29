<?php
// account_get.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$id) respond(false, 'id required', null, 400);

$stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$res = $stmt->get_result();
if(!$res || $res->num_rows === 0) respond(false, 'not found', null, 404);
respond(true, 'account', $res->fetch_assoc());
