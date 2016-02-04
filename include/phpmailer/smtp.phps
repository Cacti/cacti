<?php
/**
 * This example shows making an SMTP connection with authentication.
 */

//SMTP needs accurate times, and the PHP time zone MUST be set
//This should be done in your php.ini, but this is how to do it if you don't have access to that
date_default_timezone_set('Etc/UTC');

require './PHPMailerAutoload.php';

$mail = new PHPMailer;
$mail->isSMTP();
$mail->SMTPDebug = 0;
$mail->Debugoutput = 'html';
$mail->Host = "sslv3://mail.cacti.net";
$mail->Port = 465;
$mail->SMTPAuth = true;
$mail->Secure = 'sslv3';
$mail->Username = "thewitness@cacti.net";
$mail->Password = "Iam@1hobart";
$mail->setFrom('thewitness@cacti.net', 'Larry Adams');
$mail->addAddress('thewitness@cacti.net', 'TheWintess');
$mail->Subject = 'PHPMailer SMTP test';
$mail->Body = 'This is a plain-text message body';
$mail->AltBody = 'This is a plain-text message body';

//send the message, check for errors
if (!$mail->send()) {
    echo "Mailer Error: " . $mail->ErrorInfo;
} else {
    echo "Message sent!";
}
