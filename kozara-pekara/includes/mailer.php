<?php

require_once __DIR__ . '/config.php';

function appSendPlainEmailToMany(array $emails, string $subject, string $message): int {
    $emails = array_values(array_unique(array_filter(array_map('trim', $emails), function ($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    })));

    if (empty($emails)) {
        return 0;
    }

    if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
        $smtpSent = appSendViaSmtpWithPhpMailer($emails, $subject, $message);
        if ($smtpSent > 0) {
            return $smtpSent;
        }
    }

    return appSendViaPhpMail($emails, $subject, $message);
}

function appSendViaSmtpWithPhpMailer(array $emails, string $subject, string $message): int {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return 0;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)SMTP_HOST;
        $mail->Port = (int)SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = (string)SMTP_USERNAME;
        $mail->Password = (string)SMTP_PASSWORD;

        $secure = strtolower((string)SMTP_SECURE);
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $fromAddress = defined('MAIL_FROM_ADDRESS') && MAIL_FROM_ADDRESS !== '' ? MAIL_FROM_ADDRESS : ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromName = defined('MAIL_FROM_NAME') && MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : 'Pekara';
        $mail->setFrom($fromAddress, $fromName);

        $sent = 0;
        foreach ($emails as $email) {
            $mail->clearAddresses();
            $mail->addAddress($email);
            if ($mail->send()) {
                $sent++;
            }
        }

        return $sent;
    } catch (Throwable $e) {
        error_log('SMTP mail error: ' . $e->getMessage());
        return 0;
    }
}

function appSendViaPhpMail(array $emails, string $subject, string $message): int {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $fromAddress = defined('MAIL_FROM_ADDRESS') && MAIL_FROM_ADDRESS !== '' ? MAIL_FROM_ADDRESS : ('no-reply@' . $host);
    $fromName = defined('MAIL_FROM_NAME') && MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : 'Pekara';

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddress . '>',
    ];

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $sent = 0;

    foreach ($emails as $email) {
        if (@mail($email, $encodedSubject, $message, implode("\r\n", $headers))) {
            $sent++;
        }
    }

    return $sent;
}
