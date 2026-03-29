<?php
require_once __DIR__ . '/vendor/autoload.php';

use Razorpay\Api\Api;

require_once __DIR__ . '/helpers.php';
$conn = db_connect();
$user = validate_token($conn);
if (!$user) respond(false, 'unauthorized', null, 401);

$input = get_json_input();

$paymentId = $input['gateway_payment_id'] ?? null;
$amount    = $input['amount'] ?? null;
$method    = $input['method'] ?? null;

if (!$paymentId || !$amount || $method !== 'razorpay') {
    respond(false, 'invalid request', null, 400);
}

$api = new Api("RAZORPAY_KEY", "RAZORPAY_SECRET");

try {
    $payment = $api->payment->fetch($paymentId);

    if ($payment->status !== 'captured') {
        respond(false, 'payment not verified', null, 400);
    }

    $gatewayResp = json_encode($payment);

    $stmt = $conn->prepare(
        "INSERT INTO payments (user_id, amount, method, status, gateway_response)
         VALUES (?, ?, 'razorpay', 'success', ?)"
    );

    $paidAmount = $payment->amount / 100; // paise → rupees
    $stmt->bind_param('ids', $user['id'], $paidAmount, $gatewayResp);
    $stmt->execute();

    respond(true, 'payment successful', [
        'payment_id' => $paymentId
    ]);

} catch (Exception $e) {
    respond(false, 'verification failed', null, 500);
}
