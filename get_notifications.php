<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user) {

    respond(false, 'Unauthorized', null, 401);
}

/*
|--------------------------------------------------------------------------
| FETCH SMART FILTERED NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("

SELECT
    n.id,
    n.title,
    n.message,
    n.type,
    n.related_id,
    n.is_read,
    n.created_at

FROM notifications n

WHERE n.user_id = ?

AND (

    /*
    |----------------------------------------------------------------------
    | TASK REMINDERS
    |----------------------------------------------------------------------
    */

    (

        n.type = 'task_reminder'

        AND n.created_at >=
            DATE_SUB(NOW(), INTERVAL 1 DAY)

        AND NOT EXISTS (

            SELECT 1

            FROM task_instances ti

            WHERE
                ti.task_id = n.related_id
                AND ti.completed = 1
                AND DATE(ti.instance_date) = CURDATE()
        )
    )

    OR

    /*
    |----------------------------------------------------------------------
    | HABIT REMINDERS
    |----------------------------------------------------------------------
    */

    (

        n.type = 'habit_reminder'

        AND DATE(n.created_at) = CURDATE()
    )

    OR

    /*
    |----------------------------------------------------------------------
    | AI INSIGHTS
    |----------------------------------------------------------------------
    */

    (

        n.type = 'ai_insight'

        AND n.created_at >=
            DATE_SUB(NOW(), INTERVAL 7 DAY)
    )

    OR

    /*
    |----------------------------------------------------------------------
    | OVERDUE TASKS
    |----------------------------------------------------------------------
    */

    (

        n.type = 'task_overdue'

        AND NOT EXISTS (

            SELECT 1

            FROM task_instances ti

            WHERE
                ti.task_id = n.related_id
                AND ti.completed = 1
        )
    )
)

ORDER BY n.created_at DESC

");

$stmt->bind_param(
    'i',
    $user['id']
);

$stmt->execute();

$result =
    $stmt->get_result();

$notifications = [];

while(
    $row = $result->fetch_assoc()
) {

    $notifications[] = $row;
}

respond(
    true,
    'Notifications',
    $notifications
);