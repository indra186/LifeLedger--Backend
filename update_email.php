<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$input = get_json_input();

$userId = intval($input['user_id'] ?? 0);

$newEmail = trim($input['email'] ?? '');

$otp = trim($input['otp'] ?? '');

if(!$userId || !$newEmail || !$otp) {

    respond(false, 'All fields required', null, 400);
}

if(!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {

    respond(false, 'Invalid email format', null, 400);
}

/*
CHECK OTP
*/

$stmt = $conn->prepare(
    "SELECT id, expires_at
     FROM otps
     WHERE email = ?
     AND otp_code = ?
     AND used = 0
     ORDER BY id DESC
     LIMIT 1"
);

$stmt->bind_param(
    'ss',
    $newEmail,
    $otp
);

$stmt->execute();

$res = $stmt->get_result();

if($res->num_rows == 0) {

    respond(false, 'Invalid OTP', null, 401);
}

$row = $res->fetch_assoc();

/*
CHECK EXPIRY
*/

if(strtotime($row['expires_at']) < time()) {

    respond(false, 'OTP expired', null, 410);
}

/*
MARK OTP USED
*/

$otpId = $row['id'];

$conn->query(
    "UPDATE otps
     SET used = 1
     WHERE id = $otpId"
);

/*
CHECK EMAIL EXISTS
*/

$check = $conn->prepare(
    "SELECT id FROM users
     WHERE email = ?
     LIMIT 1"
);

$check->bind_param(
    's',
    $newEmail
);

$check->execute();

$exists = $check->get_result();

if($exists->num_rows > 0) {

    respond(false, 'Email already exists', null, 409);
}

/*
UPDATE EMAIL
*/

$update = $conn->prepare(
    "UPDATE users
     SET email = ?
     WHERE id = ?"
);

$update->bind_param(
    'si',
    $newEmail,
    $userId
);

if($update->execute()) {

    respond(
        true,
        'Email updated successfully'
    );

} else {

    respond(
        false,
        'Failed to update email',
        null,
        500
    );
}