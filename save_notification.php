<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user) {

    respond(false, 'Unauthorized', null, 401);
}

$input = get_json_input();

$title =
    trim($input['title'] ?? '');

$message =
    trim($input['message'] ?? '');

$type =
    trim($input['type'] ?? '');

$relatedId =
    isset($input['related_id'])
        ? (int)$input['related_id']
        : null;

if(
    $title === '' ||
    $message === '' ||
    $type === ''
) {

    respond(false, 'Missing fields', null, 400);
}

$stmt = $conn->prepare("
    INSERT INTO notifications (

        user_id,
        title,
        message,
        type,
        related_id

    ) VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param(

    'isssi',

    $user['id'],
    $title,
    $message,
    $type,
    $relatedId
);

if($stmt->execute()) {

    respond(true, 'Notification saved');

} else {

    respond(false, 'Failed');
}