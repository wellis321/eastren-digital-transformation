<?php
declare(strict_types=1);

/**
 * Send the 6-digit activation code to a new user.
 *
 * In local dev (APP_ENV=local) the code is also written to the PHP error log
 * so you can test without a working mail relay.
 */
function send_activation_email(string $to, string $name, string $code, int $userId): void
{
    $fromAddr    = getenv('MAIL_FROM') ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $siteName    = 'ERC Housing Digital Transformation';
    $activateUrl = APP_URL . '/activate.php?uid=' . $userId . '&token=' . urlencode($code);
    $loginUrl    = APP_URL . '/login.php';

    $subject = 'Your portal access code';
    $body    = "Hi {$name},\r\n\r\n"
             . "An account has been created for you on the Housing Digital Transformation portal.\r\n\r\n"
             . "Your one-time access code is:\r\n\r\n"
             . "    {$code}\r\n\r\n"
             . "This code expires in 30 minutes.\r\n\r\n"
             . "Click the link below to go straight to the activation page:\r\n"
             . "{$activateUrl}\r\n\r\n"
             . "If the link doesn't work, go to {$loginUrl}, enter your username\r\n"
             . "with the password left blank, and you'll be taken to the activation page.\r\n\r\n"
             . "If you did not expect this email, please ignore it.\r\n\r\n"
             . "-- {$siteName}";

    $headers  = "From: {$siteName} <{$fromAddr}>\r\n";
    $headers .= "Reply-To: {$fromAddr}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    @mail($to, $subject, $body, $headers);

    if (APP_ENV === 'local') {
        error_log("[ERC activation code] To: {$to} | Code: {$code}");
    }
}
