<?php

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/send_mail.php';

$conn = db_connect();

$input = get_json_input();

$email = trim($input['email'] ?? '');

if(!$email) {

    respond(false, 'Email required', null, 400);
}

/*
CHECK EMAIL EXISTS
*/

$check = $conn->prepare(

    "SELECT id
    FROM users
    WHERE email=?"

);

$check->bind_param(
    "s",
    $email
);

$check->execute();

$result = $check->get_result();

if($result->num_rows == 0) {

    respond(
        false,
        'Email not registered',
        null,
        404
    );
}

/*
GENERATE OTP
*/

$otp = str_pad(
    random_int(0, 999999),
    6,
    '0',
    STR_PAD_LEFT
);

$expires = date(
    'Y-m-d H:i:s',
    time() + 600
);

$stmt = $conn->prepare(

    "INSERT INTO otps
    (email, otp_code, expires_at, used)

    VALUES (?, ?, ?, 0)"
);

$stmt->bind_param(
    'sss',
    $email,
    $otp,
    $expires
);

if($stmt->execute()) {

    sendOtpMail($email, $otp);

    respond(
        true,
        'OTP sent'
    );
}

respond(false, 'Failed');