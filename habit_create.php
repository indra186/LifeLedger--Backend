<?php
// habit_create.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();
$name = $input['habit_name'] ?? null;
$freq = $input['frequency'] ?? 'daily';
$goal = isset($input['goal_per_day']) ? (int)$input['goal_per_day'] : 1;
$reminder = $input['reminder_time'] ?? null;
$id = isset($input['id']) ? (int)$input['id'] : 0;

if(!$name) respond(false, 'habit_name required', null, 400);

if($id){
    $stmt = $conn->prepare("UPDATE habits SET name = ?, frequency = ?, goal_per_day = ?, reminder_time = ?, active = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ssisii', $name, $freq, $goal, $reminder, $id, $user['id']);
    $stmt->execute();
    respond(true, 'habit updated', null);
} else {
    $stmt = $conn->prepare("INSERT INTO habits (user_id, name, frequency, goal_per_day, reminder_time, active) VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->bind_param('issis', $user['id'], $name, $freq, $goal, $reminder);
    if($stmt->execute()){
        respond(true, 'habit created', ['id'=>$conn->insert_id], 201);
    } else {
        respond(false, 'failed', null, 500);
    }
}
