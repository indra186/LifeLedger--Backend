<?php
// health_add.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();
$metric = $input['metric'] ?? null;
$value = $input['value'] ?? null;
$date = $input['date'] ?? date('Y-m-d');

if(!$metric || $value === null) respond(false, 'metric and value required', null, 400);

$stmt = $conn->prepare("INSERT INTO health_entries (user_id, metric, value, entry_date) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $user['id'], $metric, $value, $date);
if($stmt->execute()) respond(true, 'entry saved', ['id'=>$conn->insert_id], 201);
else respond(false, 'failed', null, 500);
