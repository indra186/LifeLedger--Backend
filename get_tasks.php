<?php
require_once 'helpers.php';

$conn = db_connect();
$user = validate_token($conn);

// 🔴 HANDLE UNAUTHORIZED PROPERLY
if (!$user) {
    respond(false, 'unauthorized', null, 401);
}

$goal_id = isset($_GET['goal_id']) ? (int)$_GET['goal_id'] : 0;

if ($goal_id <= 0) {
    respond(false, 'invalid goal_id', null, 400);
}

$stmt = $conn->prepare("
    SELECT goal_id, strategy, action, status, result, retry_count
    FROM agent_tasks
    WHERE user_id = ?
    AND goal_id = ?
    AND status IN ('PENDING','FAILED')
    ORDER BY created_at DESC
    LIMIT 10
");

$stmt->bind_param("ii", $user['id'], $goal_id);
$stmt->execute();

$res = $stmt->get_result();

$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

respond(true, "tasks", $data);