<?php

require_once 'helpers.php';

$conn = db_connect();

$user = validate_token($conn);

$data =
    json_decode(
        file_get_contents("php://input"),
        true
    );

$habit_id =
    $data['habit_id'] ?? 0;

$stmt = $conn->prepare("
DELETE FROM habits
WHERE id = ?
AND user_id = ?
");

$stmt->bind_param(
    "ii",
    $habit_id,
    $user['id']
);

if($stmt->execute()) {

    respond(
        true,
        "Habit deleted"
    );

} else {

    respond(
        false,
        "Delete failed"
    );
}