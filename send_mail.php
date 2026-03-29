<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

function sendOtpMail(string $email, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'lifeledger.mobile@gmail.com';
        $mail->Password = 'pbjg sbuk qfsg cidn';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('lifeledger.mobile@gmail.com', 'LifeLedger');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your LifeLedger OTP';
        $mail->Body = "
            <h2>LifeLedger Verification</h2>
            <p>Your OTP is:</p>
            <h1 style='letter-spacing:4px;'>$otp</h1>
            <p>This OTP expires in 10 minutes.</p>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('MAIL ERROR: ' . $mail->ErrorInfo);
        return false;
    }
}
