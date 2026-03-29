<?php
// send_otp.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$input = get_json_input();
$email = trim($input['email'] ?? '');
if(!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(false, 'valid email required', null, 400);

// generate 6-digit code
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 10*60); // 10 min

$stmt = $conn->prepare("INSERT INTO otps (email, otp_code, expires_at, used) VALUES (?, ?, ?, 0)");
$stmt->bind_param('sss', $email, $otp, $expires);
if($stmt->execute()){
    // NOTE: send via email in production. Here we return the OTP for testing.
    respond(true, 'otp generated (in production, emailed)', ['otp'=>$otp, 'expires_at'=>$expires]);
} else {
    respond(false, 'failed to generate otp', null, 500);
}
