<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$today =
    date('Y-m-d');

$currentDay =
    date('D');

$currentDate =
    date('d');

/*
|--------------------------------------------------------------------------
| GET TASKS
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("

    SELECT *

    FROM tasks
");

$stmt->execute();

$result =
    $stmt->get_result();

while($task = $result->fetch_assoc()) {

    $shouldCreate = false;

    switch($task['repeat_type']) {

        case 'daily':

            $shouldCreate = true;

            break;

        case 'weekly':

            if(
                strpos(
                    $task['repeat_days'],
                    $currentDay
                ) !== false
            ) {

                $shouldCreate = true;
            }

            break;

        case 'monthly':

            $taskDay =
                date(
                    'd',
                    strtotime($task['date'])
                );

            if($taskDay == $currentDate) {

                $shouldCreate = true;
            }

            break;

        default:

            if($task['date'] == $today) {

                $shouldCreate = true;
            }
    }

    if(!$shouldCreate)
        continue;

    /*
    |--------------------------------------------------------------------------
    | PREVENT DUPLICATES
    |--------------------------------------------------------------------------
    */

    $checkStmt = $conn->prepare("

        SELECT id

        FROM task_instances

        WHERE
            task_id = ?
            AND instance_date = ?

        LIMIT 1
    ");

    $checkStmt->bind_param(

        'is',

        $task['id'],

        $today
    );

    $checkStmt->execute();

    $exists =
        $checkStmt
            ->get_result()
            ->fetch_assoc();

    if($exists)
        continue;

    /*
    |--------------------------------------------------------------------------
    | CREATE INSTANCE
    |--------------------------------------------------------------------------
    */

    $insertStmt = $conn->prepare("

        INSERT INTO task_instances (

            task_id,
            instance_date,
            completed,
            skipped

        )

        VALUES (?, ?, 0, 0)
    ");

    $insertStmt->bind_param(

        'is',

        $task['id'],

        $today
    );

    $insertStmt->execute();
}

echo "done";