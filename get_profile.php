<?php
// get_profile.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$stmt = $conn->prepare("SELECT id, name, email, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
if(!$res || $res->num_rows === 0) respond(false, 'user not found', null, 404);
$u = $res->fetch_assoc();

$prefSt = $conn->prepare("SELECT currency, goals_json, theme, accent_color, display_flags FROM user_preferences WHERE user_id = ? LIMIT 1");
$prefSt->bind_param('i', $user['id']);
$prefSt->execute();
$p = $prefSt->get_result()->fetch_assoc();

respond(true, 'profile', ['user'=>$u, 'preferences'=>$p]);
