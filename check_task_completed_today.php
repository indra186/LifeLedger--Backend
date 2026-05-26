<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user){

    respond(false, 'Unauthorized', null, 401);
}

$taskId =
    (int)($_GET['task_id'] ?? 0);

$today =
    date('Y-m-d');

$stmt = $conn->prepare("

    SELECT completed

    FROM task_instances

    WHERE
        task_id = ?
        AND instance_date = ?

    LIMIT 1
");

$stmt->bind_param(
    'is',
    $taskId,
    $today
);

$stmt->execute();

$result =
    $stmt->get_result();

$row =
    $result->fetch_assoc();

respond(true, 'status', [

    'completed' =>
        ($row['completed'] ?? 0) == 1
]);