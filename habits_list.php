<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user){
    respond(false, 'unauthorized', null, 401);
}

$stmt = $conn->prepare("
SELECT
    h.id,
    h.name,
    h.description,
    h.icon,
    h.frequency,
    h.selected_days,
    h.goal_per_day,
    h.goal_unit,
    h.reminder_time,

    EXISTS(
        SELECT 1
        FROM habit_logs hl
        WHERE
            hl.habit_id = h.id
            AND hl.completed_date = CURDATE()
    ) AS completed_today,

    COALESCE(
        (
            SELECT COUNT(*)
            FROM habit_logs
            WHERE habit_id = h.id
        ),
        0
    ) AS streak

FROM habits h

WHERE h.user_id = ?
");

$stmt->bind_param(
    'i',
    $user['id']
);

$stmt->execute();

$res = $stmt->get_result();

$out = [];

while($r = $res->fetch_assoc()){

    $out[] = $r;
}

respond(
    true,
    'habits fetched',
    $out
);