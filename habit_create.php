<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user){
    respond(false, 'unauthorized', null, 401);
}

$input = get_json_input();

$name =
    trim($input['habit_name'] ?? '');

$description =
    trim($input['description'] ?? '');

$icon =
    trim($input['icon'] ?? 'fitness');

$frequency =
    trim($input['frequency'] ?? 'daily');

$selected_days =
    trim($input['selected_days'] ?? '');

$goal =
    isset($input['goal_per_day'])
        ? (int)$input['goal_per_day']
        : 1;

$goal_unit =
    trim($input['goal_unit'] ?? 'times');

$reminder =
    trim($input['reminder_time'] ?? '');

$id =
    isset($input['id'])
        ? (int)$input['id']
        : 0;

if($name === ''){
    respond(false, 'Habit name required', null, 400);
}

$allowed_icons = [
    'fitness',
    'reading',
    'meditation',
    'water',
    'running',
    'art',
    'music',
    'journal',
    'nature',
    'sleep'
];

if(!in_array($icon, $allowed_icons)){
    $icon = 'fitness';
}

if($id > 0){

    $stmt = $conn->prepare("
        UPDATE habits
        SET
            name = ?,
            description = ?,
            icon = ?,
            frequency = ?,
            selected_days = ?,
            goal_per_day = ?,
            goal_unit = ?,
            reminder_time = ?,
            active = 1
        WHERE id = ?
        AND user_id = ?
    ");

    $stmt->bind_param(
        'sssssissii',
        $name,
        $description,
        $icon,
        $frequency,
        $selected_days,
        $goal,
        $goal_unit,
        $reminder,
        $id,
        $user['id']
    );

    if($stmt->execute()){

        respond(true, 'Habit updated', null);

    } else {

        respond(false, 'Update failed', null, 500);
    }

} else {

    $stmt = $conn->prepare("
        INSERT INTO habits (
            user_id,
            name,
            description,
            icon,
            frequency,
            selected_days,
            goal_per_day,
            goal_unit,
            reminder_time,
            active
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->bind_param(
        'isssssiss',
        $user['id'],
        $name,
        $description,
        $icon,
        $frequency,
        $selected_days,
        $goal,
        $goal_unit,
        $reminder
    );

    if($stmt->execute()){

        respond(
            true,
            'Habit created',
            [
                'id' => $conn->insert_id
            ],
            201
        );

    } else {

        respond(false, 'Creation failed', null, 500);
    }
}