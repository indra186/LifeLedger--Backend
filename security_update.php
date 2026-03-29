<?php
// security_update.php
require_once __DIR__.'/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if(!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();
$new_password = $input['password'] ?? null;
$biometric = isset($input['biometric']) ? $input['biometric'] : null;

if($new_password){
    $hash = hash_password($new_password);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $user['id']);
    $stmt->execute();
}

if($biometric !== null){
    // store in settings.notifications or separate column (simple example)
    $stmt2 = $conn->prepare("INSERT INTO settings (user_id, notifications) VALUES (?, ?) ON DUPLICATE KEY UPDATE notifications = ?");
    $b = json_encode(['biometric'=>$biometric]);
    $stmt2->bind_param('iss', $user['id'], $b, $b);
    $stmt2->execute();
}

respond(true, 'security updated');
