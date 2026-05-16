<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user){
    respond(false, 'unauthorized', null, 401);
}

$habit_id =
    isset($_GET['habit_id'])
    ? (int)$_GET['habit_id']
    : 0;

if($habit_id <= 0){

    respond(false, 'invalid habit id', null, 400);
}

$stmt = $conn->prepare("
SELECT
    completed_date
FROM habit_logs
WHERE habit_id = ?
AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
");

$stmt->bind_param(
    'i',
    $habit_id
);

$stmt->execute();

$res = $stmt->get_result();

$history = [];

while($row = $res->fetch_assoc()){

    $history[] =
        $row['completed_date'];
}

respond(
    true,
    'history fetched',
    $history
);