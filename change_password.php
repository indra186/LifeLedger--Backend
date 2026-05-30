<?php

header('Content-Type: application/json');

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$input = get_json_input();

$userId = intval($input['user_id'] ?? 0);

$currentPassword =
    trim($input['current_password'] ?? '');

$newPassword =
    trim($input['new_password'] ?? '');

if(
    !$userId ||
    !$currentPassword ||
    !$newPassword
) {

    respond(
        false,
        'All fields required',
        null,
        400
    );
}

/*
GET USER
*/

$stmt = $conn->prepare(
    "SELECT password_hash
     FROM users
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param(
    'i',
    $userId
);

$stmt->execute();

$res = $stmt->get_result();

if($res->num_rows == 0) {

    respond(
        false,
        'User not found',
        null,
        404
    );
}

$user = $res->fetch_assoc();

/*
VERIFY CURRENT PASSWORD
*/

if(
    !password_verify(
        $currentPassword,
        $user['password_hash']
    )
) {

    respond(
        false,
        'Current password incorrect',
        null,
        401
    );
}

/*
HASH NEW PASSWORD
*/

$newHash =
    password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

/*
UPDATE PASSWORD
*/

$update = $conn->prepare(
    "UPDATE users
     SET password_hash = ?
     WHERE id = ?"
);

$update->bind_param(
    'si',
    $newHash,
    $userId
);

if($update->execute()) {

    respond(
        true,
        'Password updated successfully'
    );

} else {

    respond(
        false,
        'Failed to update password',
        null,
        500
    );
}