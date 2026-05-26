<?php

require_once 'helpers.php';

$conn = db_connect();

$user = validate_token($conn);

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$task_id = $data['task_id'];

$stmt = $conn->prepare("
DELETE FROM tasks
WHERE id = ?
AND user_id = ?
");

$stmt->bind_param(
    "ii",
    $task_id,
    $user['id']
);

$stmt->execute();

respond(
    true,
    "Task deleted"
);