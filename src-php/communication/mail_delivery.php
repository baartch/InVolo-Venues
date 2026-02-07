<?php
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/email_helpers.php';

function sendEmailViaMailbox(PDO $pdo, array $mailbox, array $payload): bool
{
    $smtpPassword = decryptSettingValue($mailbox['smtp_password'] ?? '');
    if ($smtpPassword === '') {
        return false;
    }

    $host = (string) ($mailbox['smtp_host'] ?? '');
    $port = (int) ($mailbox['smtp_port'] ?? 0);
    $username = (string) ($mailbox['smtp_username'] ?? '');
    $encryption = (string) ($mailbox['smtp_encryption'] ?? 'tls');

    if ($host === '' || $port <= 0 || $username === '') {
        return false;
    }

    $fromEmail = (string) ($payload['from_email'] ?? $username);
    $toList = splitEmailList((string) ($payload['to_emails'] ?? ''));
    $ccList = splitEmailList((string) ($payload['cc_emails'] ?? ''));
    $bccList = splitEmailList((string) ($payload['bcc_emails'] ?? ''));
    $subject = (string) ($payload['subject'] ?? '');
    $body = (string) ($payload['body'] ?? '');

    if (!$toList) {
        return false;
    }

    $recipients = array_values(array_unique(array_merge($toList, $ccList, $bccList)));

    $isHtml = $body !== strip_tags($body);
    $contentType = $isHtml ? 'text/html' : 'text/plain';

    $headers = [
        'From: ' . $fromEmail,
        'To: ' . implode(', ', $toList),
        'Subject: ' . $subject,
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: ' . $contentType . '; charset=UTF-8'
    ];

    if ($ccList) {
        $headers[] = 'Cc: ' . implode(', ', $ccList);
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $message = preg_replace("/\r\n\. /", "\r\n..", $message);

    $connection = smtpOpenConnection($host, $port, $encryption);
    if (!$connection) {
        return false;
    }

    [$socket, $capabilities] = $connection;

    if (!smtpExpect($socket, 220)) {
        smtpClose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'EHLO ' . gethostname(), 250)) {
        smtpClose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtpCommand($socket, 'STARTTLS', 220)) {
            smtpClose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            smtpClose($socket);
            return false;
        }
        if (!smtpCommand($socket, 'EHLO ' . gethostname(), 250)) {
            smtpClose($socket);
            return false;
        }
    }

    if (!smtpCommand($socket, 'AUTH LOGIN', 334)) {
        smtpClose($socket);
        return false;
    }
    if (!smtpCommand($socket, base64_encode($username), 334)) {
        smtpClose($socket);
        return false;
    }
    if (!smtpCommand($socket, base64_encode($smtpPassword), 235)) {
        smtpClose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250)) {
        smtpClose($socket);
        return false;
    }

    foreach ($recipients as $recipient) {
        if (!smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', 250)) {
            smtpClose($socket);
            return false;
        }
    }

    if (!smtpCommand($socket, 'DATA', 354)) {
        smtpClose($socket);
        return false;
    }

    fwrite($socket, $message . "\r\n.\r\n");
    if (!smtpExpect($socket, 250)) {
        smtpClose($socket);
        return false;
    }

    smtpCommand($socket, 'QUIT', 221);
    smtpClose($socket);

    return true;
}

function smtpOpenConnection(string $host, int $port, string $encryption): ?array
{
    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        return null;
    }

    stream_set_timeout($socket, 10);
    return [$socket, []];
}

function smtpCommand($socket, string $command, int $expectCode): bool
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectCode);
}

function smtpExpect($socket, int $expectCode): bool
{
    $response = '';
    while (($line = fgets($socket, 512)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ($response === '') {
        return false;
    }

    $code = (int) substr($response, 0, 3);
    return $code === $expectCode;
}

function smtpClose($socket): void
{
    if (is_resource($socket)) {
        fclose($socket);
    }
}
