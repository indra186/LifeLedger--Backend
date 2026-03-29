<?php
// habit_progress_list.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$habit_id = isset($_GET['habit_id']) ? (int)$_GET['habit_id'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

if($habit_id <= 0) respond(false, 'habit_id required', null, 400);

// verify habit belongs to user
$chk = $conn->prepare("SELECT id FROM habits WHERE id = ? AND user_id = ? LIMIT 1");
$chk->bind_param('ii', $habit_id, $user['id']);
$chk->execute();
$res = $chk->get_result();
if(!$res || $res->num_rows === 0) respond(false, 'habit not found', null, 404);

$stmt = $conn->prepare("SELECT id, progress_date, amount, created_at FROM habit_progress WHERE habit_id = ? AND user_id = ? ORDER BY progress_date DESC, created_at DESC LIMIT ?");
$stmt->bind_param('iii', $habit_id, $user['id'], $limit);
$stmt->execute();
$r = $stmt->get_result();
$out = [];
while($row = $r->fetch_assoc()) $out[] = $row;
respond(true, 'habit_progress', $out);
