<?php

require_once 'helpers.php';

$conn = db_connect();

$user = validate_token($conn);

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$title = $data['title'] ?? '';
$description = trim(
    $data['description'] ?? ''
);
$date = $data['date'] ?? null;
$time = $data['time'] ?? null;
$priority = $data['priority'] ?? 'medium';
$repeat_type = $data['repeat_type'] ?? 'none';
$repeat_days = $data['repeat_days'] ?? '';
$reminder_enabled =
    $data['reminder_enabled'] ?? 0;
$attachment_uri =
    $data['attachment_uri'] ?? null;

if(empty(trim($title))) {

    respond(
        false,
        "Task title required"
    );
}

if(empty($date)) {

    respond(
        false,
        "Task date required"
    );
}

if(empty($time)) {

    respond(
        false,
        "Task time required"
    );
}

$allowed_priorities = [
    'low',
    'medium',
    'high'
];

if(
    !in_array(
        $priority,
        $allowed_priorities
    )
) {

    respond(
        false,
        "Invalid priority"
    );
}

$allowed_repeat = [
    'none',
    'daily',
    'weekly',
    'monthly'
];

if(
    !in_array(
        $repeat_type,
        $allowed_repeat
    )
) {

    respond(
        false,
        "Invalid repeat type"
    );
}

if(
    $repeat_type === 'weekly' &&
    empty(trim($repeat_days))
) {

    respond(
        false,
        "Weekly repeat requires weekdays"
    );
}

$stmt = $conn->prepare("
INSERT INTO tasks (
    user_id,
    title,
    description,
    date,
    time,
    priority,
    repeat_type,
    repeat_days,
    reminder_enabled,
    attachment_uri
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isssssssis",
    $user['id'],
    $title,
    $description,
    $date,
    $time,
    $priority,
    $repeat_type,
    $repeat_days,
    $reminder_enabled,
    $attachment_uri
);

if($stmt->execute()) {

    $taskId =
        $conn->insert_id;

    $taskStmt = $conn->prepare("

        SELECT *

        FROM tasks

        WHERE id = ?

        LIMIT 1
    ");

    $taskStmt->bind_param(
        'i',
        $taskId
    );

    $taskStmt->execute();

    $task =
        $taskStmt
            ->get_result()
            ->fetch_assoc();

    respond(

        true,

        "Task created",

        $task
    );

} else {

    respond(
        false,
        "Failed to create task"
    );
}