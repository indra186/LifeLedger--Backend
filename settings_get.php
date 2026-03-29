<?php
// settings_get.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$stmt = $conn->prepare("SELECT notifications, language, timezone FROM settings WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
if($res && $res->num_rows) $settings = $res->fetch_assoc();
else $settings = null;
respond(true, 'settings', $settings);
