<?php
require_once 'helpers.php';

$conn = db_connect();
$user = validate_token($conn);

$data = json_decode(file_get_contents("php://input"), true);

$stmt = $conn->prepare("
INSERT INTO agent_tasks (user_id, goal_id, strategy, action, status)
VALUES (?, ?, ?, ?, 'PENDING')
");

$stmt->bind_param(
    "iiss",
    $user['id'],
    $data['goal_id'],
    $data['strategy'],
    $data['action']
);

$stmt->execute();

respond(true, "task created");