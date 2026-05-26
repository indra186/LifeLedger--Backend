<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if (!$user) {

    respond(false, 'Unauthorized', null, 401);
}

$habitId =
    $_GET['habit_id'] ?? 0;

$today =
    date('Y-m-d');

$stmt = $conn->prepare("
    SELECT id
    FROM habit_logs
    WHERE habit_id = ?
    AND completed_date = ?
    LIMIT 1
");

$stmt->bind_param(
    'is',
    $habitId,
    $today
);

$stmt->execute();

$result =
    $stmt->get_result();

$isCompleted =
    $result->num_rows > 0;

respond(true, 'status', [

    'completed' =>
        $isCompleted
]);