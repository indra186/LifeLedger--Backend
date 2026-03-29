<?php
// otps_list.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
// allow either admin token or basic token; keep restricted — validate token
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$email = isset($_GET['email']) ? $conn->real_escape_string($_GET['email']) : null;
$q = "SELECT id, email, otp_code, expires_at, used, created_at FROM otps";
$params = [];
if($email){
    $q .= " WHERE email = ?";
    $params[] = $email;
}
$q .= " ORDER BY created_at DESC LIMIT 200";

if($email){
    $stmt = $conn->prepare($q);
    $stmt->bind_param('s', $email);
} else {
    $stmt = $conn->prepare($q);
}
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while($r = $res->fetch_assoc()) $out[] = $r;
respond(true, 'otps', $out);
