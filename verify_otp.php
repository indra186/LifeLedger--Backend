<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/helpers.php';
$conn = db_connect();
$input = get_json_input();

$email = strtolower(trim($input['email'] ?? ''));
$otp = trim($input['otp'] ?? '');

if(!$email || !$otp) respond(false,'email and otp required',null,400);

/* Validate OTP */
$stmt = $conn->prepare(
    "SELECT id,expires_at FROM otps WHERE email=? AND otp_code=? AND used=0 ORDER BY id DESC LIMIT 1"
);
$stmt->bind_param('ss',$email,$otp);
$stmt->execute();
$res = $stmt->get_result();
if($res->num_rows==0) respond(false,'Invalid OTP',null,401);

$row = $res->fetch_assoc();
if(strtotime($row['expires_at']) < time()) respond(false,'OTP expired',null,410);

/* Mark OTP used */
$conn->query("UPDATE otps SET used=1 WHERE id=".$row['id']);

/* Create real account */
$move = $conn->prepare(
    "INSERT INTO users (name,email,password_hash,email_verified)
    SELECT name,email,password_hash,1 FROM pending_users WHERE email=?
"
);
$move->bind_param('s',$email);
$move->execute();

/* Remove pending */
$conn->query("DELETE FROM pending_users WHERE email='$email'");

respond(true,'Email verified. Account created');
