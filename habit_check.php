<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user){
    respond(false,'unauthorized',null,401);
}

$input = get_json_input();

$habit_id =
    (int)($input['habit_id'] ?? 0);

$date =
    trim($input['date'] ?? '');

if($habit_id <= 0){
    respond(false,'Invalid habit',null,400);
}
$check = $conn->prepare("
SELECT id
FROM habit_logs
WHERE
    habit_id = ?
    AND completed_date = ?
");

$check->bind_param(
    'is',
    $habit_id,
    $date
);

$check->execute();

$res = $check->get_result();

if($res->num_rows > 0){

    respond(
        true,
        'Already completed today',
        null
    );
}

$stmt = $conn->prepare("
    INSERT INTO habit_logs(
        habit_id,
        completed_date
    )
    VALUES (?, ?)
");

$stmt->bind_param(
    'is',
    $habit_id,
    $date
);

if($stmt->execute()){

    respond(true,'Habit completed');

}else{

    respond(false,'Failed',null,500);
}