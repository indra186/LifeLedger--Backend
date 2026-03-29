<?php
// tasks_list.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$stmt = $conn->prepare("SELECT id, title, description, date, time, priority, repeat_type, completed FROM tasks WHERE user_id = ? ORDER BY date ASC, time ASC");
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
respond(true, 'tasks', $out);
