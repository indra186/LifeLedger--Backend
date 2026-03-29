<?php
// health_get.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$stmt = $conn->prepare("SELECT id, metric, value, entry_date FROM health_entries WHERE user_id = ? ORDER BY entry_date DESC LIMIT 100");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
respond(true, 'health_entries', $out);
