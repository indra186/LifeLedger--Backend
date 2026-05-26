<?php

require_once 'helpers.php';

$conn = db_connect();

$user = validate_token($conn);

$today = date("Y-m-d");

$tasksQuery = $conn->prepare("

SELECT

    t.id,
    t.title,
    t.description,
    t.date,
    t.time,
    t.priority,
    t.repeat_type,
    t.repeat_days,
    t.reminder_enabled,
    t.attachment_uri,

    COALESCE(
        ti.completed,
        0
    ) AS completed

FROM tasks t

LEFT JOIN task_instances ti

ON t.id = ti.task_id
AND ti.instance_date = ?

WHERE t.user_id = ?

ORDER BY
    t.date ASC,
    t.time ASC
");

$tasksQuery->bind_param(

    "si",

    $today,

    $user['id']
);

$tasksQuery->execute();

$data =

    $tasksQuery
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

respond(

    true,

    "Tasks fetched",

    $data
);