<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '/var/www/html/app/EnvLoader.php';
EnvLoader::load('/var/www/html/.env');
$smtpHost = getenv('SMTP_HOST');
$smtpPort = getenv('SMTP_PORT');
$smtpUser = getenv('SMTP_USER');
$smtpPass = getenv('SMTP_PASS');
$smtpSecure = getenv('SMTP_SECURE');
$from = getenv('SMTP_FROM_EMAIL') ?: $smtpUser;
$to = $smtpUser;
$target = ($smtpSecure === 'ssl' ? 'ssl://' : 'tcp://') . $smtpHost . ':' . $smtpPort;
$errno = 0;
$errstr = '';
$socket = stream_socket_client($target, $errno, $errstr, 15);
if ($socket === false) {
    echo "connect_failed: target={$target} errno={$errno} errstr={$errstr}\n";
    exit(1);
}
stream_set_timeout($socket, 15);
function recv($socket) {
    $line = fgets($socket);
    echo 'S: ' . ($line === false ? '(no response)' : $line);
    return $line;
}
function sendCmd($socket, $cmd) {
    echo "C: {$cmd}\n";
    fwrite($socket, $cmd . "\r\n");
}
recv($socket);
sendCmd($socket, "EHLO localhost");
while (($line = fgets($socket)) !== false) {
    echo 'S: ' . $line;
    if (preg_match('/^\d{3} /', $line)) {
        break;
    }
}
if ($smtpSecure === 'tls') {
    sendCmd($socket, 'STARTTLS');
    recv($socket);
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        echo "enable_crypto_failed\n";
        fclose($socket);
        exit(1);
    }
    sendCmd($socket, "EHLO localhost");
    while (($line = fgets($socket)) !== false) {
        echo 'S: ' . $line;
        if (preg_match('/^\d{3} /', $line)) {
            break;
        }
    }
}
if ($smtpUser !== '' && $smtpPass !== '') {
    sendCmd($socket, 'AUTH LOGIN');
    recv($socket);
    sendCmd($socket, base64_encode($smtpUser));
    recv($socket);
    sendCmd($socket, base64_encode($smtpPass));
    $authResp = recv($socket);
    echo "authResp={$authResp}";
    if (strpos($authResp, '235') !== 0) {
        echo "auth_failed\n";
        fclose($socket);
        exit(1);
    }
}
sendCmd($socket, "MAIL FROM:<{$from}>");
recv($socket);
sendCmd($socket, "RCPT TO:<{$to}>");
recv($socket);
sendCmd($socket, 'DATA');
recv($socket);
$message = "From: {$from}\r\nTo: {$to}\r\nSubject: Teste SMTP Gmail\r\n\r\nCorpo do teste.\r\n.";
fwrite($socket, $message . "\r\n");
recv($socket);
sendCmd($socket, 'QUIT');
recv($socket);
fclose($socket);
echo "smtp_send_complete\n";
