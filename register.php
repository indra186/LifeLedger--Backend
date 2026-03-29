<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__.'/helpers.php';
require_once __DIR__.'/send_mail.php';

$conn = db_connect();
$input = get_json_input();

$name = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if(!$name || !$email || !$password){
    respond(false,'All fields required',null,400);
}

/* Block if already registered */
$chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chk->bind_param('s',$email);
$chk->execute();
$chk->store_result();
if($chk->num_rows > 0){
    respond(false,'Email already registered',null,409);
}

/* Remove old pending if exists */
$conn->query("DELETE FROM pending_users WHERE email='$email'");

/* Save unverified user */
$hash = password_hash($password,PASSWORD_BCRYPT);
$stmt = $conn->prepare(
    "INSERT INTO pending_users (name,email,password_hash) VALUES (?,?,?)"
);
$stmt->bind_param('sss',$name,$email,$hash);
$stmt->execute();

/* Generate OTP */
$otp = strval(random_int(100000,999999));
$expires = date('Y-m-d H:i:s', time()+600);

$conn->query("DELETE FROM otps WHERE email='$email'");
$otpStmt = $conn->prepare(
    "INSERT INTO otps (email,otp_code,expires_at,used) VALUES (?,?,?,0)"
);
$otpStmt->bind_param('sss',$email,$otp,$expires);
$otpStmt->execute();

/* Send email */
if(!sendOtpMail($email,$otp)){
    respond(false,'OTP email failed',null,500);
}

respond(true,'OTP sent to email',['email'=>$email]);
