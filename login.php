<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/helpers.php';

$conn  = db_connect();
$input = get_json_input();

$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    respond(false, 'Email and password required', null, 400);
}

$stmt = $conn->prepare(
    "SELECT id, name, email, password_hash FROM users 
WHERE email = ? AND email_verified = 1
LIMIT 1
"
);
$stmt->bind_param('s', $email);
$stmt->execute();
    
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    respond(false, 'Email not verified. Please verify OTP.', null, 403);
}


$user = $result->fetch_assoc();

if (!password_verify($password, $user['password_hash'])) {
    respond(false, 'Invalid email or password', null, 401);
}

/* Generate fresh token */
$token = generate_token();

$update = $conn->prepare(
    "UPDATE users SET auth_token = ? WHERE id = ?"
);
$update->bind_param('si', $token, $user['id']);
$update->execute();


respond(true, 'Login successful', [

    'user_id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'auth_token' => $token
]);
