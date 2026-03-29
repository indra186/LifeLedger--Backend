<?php
// habit_progress_add.php (corrected)
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();
$habit_id = isset($input['habit_id']) ? (int)$input['habit_id'] : 0;
$amount = isset($input['amount']) ? (int)$input['amount'] : 1;
$date = isset($input['date']) && $input['date'] !== '' ? $input['date'] : date('Y-m-d');

if($habit_id <= 0) respond(false, 'habit_id required and must be a positive integer', null, 400);

// verify habit exists and belongs to this user
$chk = $conn->prepare("SELECT id FROM habits WHERE id = ? AND user_id = ? LIMIT 1");
if(!$chk) respond(false, 'prepare failed: '.$conn->error, null, 500);
$chk->bind_param('ii', $habit_id, $user['id']);
$chk->execute();
$cres = $chk->get_result();
if(!$cres || $cres->num_rows === 0){
    $chk->close();
    respond(false, 'habit_id not found for this user', null, 400);
}
$chk->close();

// insert progress
$stmt = $conn->prepare("INSERT INTO habit_progress (habit_id, user_id, progress_date, amount) VALUES (?, ?, ?, ?)");
if(!$stmt) respond(false, 'prepare failed: '.$conn->error, null, 500);
$stmt->bind_param('iisi', $habit_id, $user['id'], $date, $amount);

if($stmt->execute()){
    $id = $conn->insert_id;
    $stmt->close();
    respond(true, 'progress saved', ['id' => $id], 201);
} else {
    $err = $stmt->error ?: $conn->error;
    $stmt->close();
    respond(false, 'insert failed: '.$err, null, 500);
}
