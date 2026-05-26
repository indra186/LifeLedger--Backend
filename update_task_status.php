<?php

require_once 'helpers.php';

$conn = db_connect();

$user = validate_token($conn);

$data = json_decode(

    file_get_contents("php://input"),

    true
);

$task_id =
    $data['task_id'] ?? 0;

$completed =
    $data['completed'] ?? 0;

$today =
    date("Y-m-d");

/*
|--------------------------------------------------------------------------
| CHECK IF INSTANCE EXISTS
|--------------------------------------------------------------------------
*/

$check = $conn->prepare("

SELECT id

FROM task_instances

WHERE
    task_id = ?
    AND instance_date = ?
");

$check->bind_param(

    "is",

    $task_id,

    $today
);

$check->execute();

$result =
    $check->get_result();

/*
|--------------------------------------------------------------------------
| UPDATE EXISTING INSTANCE
|--------------------------------------------------------------------------
*/

if($result->num_rows > 0) {

    $update = $conn->prepare("

    UPDATE task_instances

    SET

        completed = ?,

        completed_at =

            CASE
                WHEN ? = 1
                THEN NOW()
                ELSE NULL
            END

    WHERE
        task_id = ?
        AND instance_date = ?
    ");

    $update->bind_param(

        "iiis",

        $completed,

        $completed,

        $task_id,

        $today
    );

    $update->execute();

}

/*
|--------------------------------------------------------------------------
| CREATE NEW INSTANCE
|--------------------------------------------------------------------------
*/

else {

    $insert = $conn->prepare("

    INSERT INTO task_instances (

        task_id,
        instance_date,
        completed,
        completed_at

    )

    VALUES (?, ?, ?, ?)
    ");

    $completedAt =

        $completed == 1
            ? date("Y-m-d H:i:s")
            : null;

    $insert->bind_param(

        "isis",

        $task_id,

        $today,

        $completed,

        $completedAt
    );

    $insert->execute();
}

respond(

    true,

    "Task updated"
);