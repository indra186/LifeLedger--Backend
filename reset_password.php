<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$input = get_json_input();

$email =
    trim($input['email'] ?? '');

$otp =
    trim($input['otp'] ?? '');

$newPassword =
    trim($input['new_password'] ?? '');

if(!$email || !$otp || !$newPassword) {

    respond(false, 'Missing fields', null, 400);
}

/*
VERIFY OTP
*/

$stmt = $conn->prepare(

    "SELECT id
    FROM otps

    WHERE email=?
    AND otp_code=?
    AND used=0

    ORDER BY id DESC
    LIMIT 1"
);

$stmt->bind_param(
    "ss",
    $email,
    $otp
);

$stmt->execute();

$res = $stmt->get_result();

if($res->num_rows == 0) {

    respond(false, 'Invalid OTP', null, 401);
}

/*
UPDATE PASSWORD
*/

$hash =
    password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

$update = $conn->prepare(

    "UPDATE users
    SET password_hash=?
    WHERE email=?"
);

$update->bind_param(
    "ss",
    $hash,
    $email
);

if($update->execute()) {

    respond(true, 'Password reset successful');
}

respond(false, 'Failed');