<?php

require_once __DIR__.'/helpers.php';

$conn = db_connect();

$input = get_json_input();

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');

if(!$email) {
    respond(false, 'Email required', null, 400);
}

/*
CHECK USER EXISTS
*/

$stmt = $conn->prepare(
    "SELECT id,name,email,auth_token
     FROM users
     WHERE email=?"
);

$stmt->bind_param("s", $email);

$stmt->execute();

$result = $stmt->get_result();

/*
IF USER EXISTS
*/

if($result->num_rows > 0) {

    $user = $result->fetch_assoc();

    respond(true, 'Login success', [
        'user_id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'auth_token' => $user['auth_token']
    ]);
}

/*
CREATE NEW GOOGLE USER
*/

$token = bin2hex(random_bytes(32));

$dummyPassword =
    password_hash(
        uniqid(),
        PASSWORD_BCRYPT
    );

$insert = $conn->prepare(
    "INSERT INTO users
    (name,email,password_hash,auth_token,email_verified)
    VALUES(?,?,?,?,1)"
);

$insert->bind_param(
    "ssss",
    $name,
    $email,
    $dummyPassword,
    $token
);

if($insert->execute()) {

    $userId = $insert->insert_id;

    respond(true, 'Google signup success', [

        'user_id' => $userId,
        'name' => $name,
        'email' => $email,
        'auth_token' => $token
    ]);
}

respond(false, 'Google login failed', null, 500);