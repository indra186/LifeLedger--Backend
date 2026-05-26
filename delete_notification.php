<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$user = validate_token($conn);

if(!$user) {

    respond(false, 'Unauthorized', null, 401);
}

$input = get_json_input();

$notificationId =
    (int)($input['notification_id'] ?? 0);

if($notificationId <= 0) {

    respond(false, 'Invalid notification');
}

$stmt = $conn->prepare("

    DELETE FROM notifications

    WHERE
        id = ?
        AND user_id = ?

");

$stmt->bind_param(
    'ii',
    $notificationId,
    $user['id']
);

if($stmt->execute()) {

    respond(true, 'Notification deleted');

} else {

    respond(false, 'Delete failed');
}