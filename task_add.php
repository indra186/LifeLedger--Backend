<?php
// task_add.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();
$title = $input['title'] ?? null;
$desc = $input['description'] ?? null;
$date = $input['date'] ?? null;
$time = $input['time'] ?? null;
$priority = $input['priority'] ?? 'medium';
$repeat = $input['repeat_type'] ?? null;
$id = isset($input['id']) ? (int)$input['id'] : 0;

if(!$title) respond(false, 'title required', null, 400);

if($id){
    $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, date=?, time=?, priority=?, repeat_type=? WHERE id=? AND user_id=?");
    $stmt->bind_param('ssssssii', $title, $desc, $date, $time, $priority, $repeat, $id, $user['id']);
    $stmt->execute();
    respond(true, 'task updated', null);
} else {
    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, date, time, priority, repeat_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssss', $user['id'], $title, $desc, $date, $time, $priority, $repeat);
    if($stmt->execute()) respond(true, 'task created', ['id'=>$conn->insert_id], 201);
    else respond(false, 'failed', null, 500);
}
