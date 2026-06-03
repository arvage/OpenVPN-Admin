<?php

class SmtpMailer {
  public static function send(array $cfg, string $to, string $subject, string $body): bool {
    $host     = $cfg['smtp_host'] ?? '';
    $port     = intval($cfg['smtp_port'] ?? 587);
    $user     = $cfg['smtp_user'] ?? '';
    $pass     = $cfg['smtp_pass'] ?? '';
    $from     = $cfg['smtp_from'] ?? '';
    $fromName = $cfg['smtp_from_name'] ?: 'OpenVPN Admin';
    $secure   = $cfg['smtp_secure'] ?? 'tls';

    if (empty($host) || empty($from)) return false;

    $errno = 0; $errstr = '';
    if ($secure === 'ssl') {
      $conn = @fsockopen("ssl://$host", $port, $errno, $errstr, 10);
    } else {
      $conn = @fsockopen($host, $port, $errno, $errstr, 10);
    }
    if (!$conn) return false;

    $r = fn() => fgets($conn, 515);
    $w = fn($cmd) => fputs($conn, $cmd . "\r\n");

    $r(); // server greeting
    $w("EHLO " . (gethostname() ?: 'localhost'));
    while ($line = $r()) { if (isset($line[3]) && $line[3] === ' ') break; }

    if ($secure === 'tls') {
      $w("STARTTLS");
      $r();
      if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($conn);
        return false;
      }
      $w("EHLO " . (gethostname() ?: 'localhost'));
      while ($line = $r()) { if (isset($line[3]) && $line[3] === ' ') break; }
    }

    if (!empty($user)) {
      $w("AUTH LOGIN");
      $r();
      $w(base64_encode($user));
      $r();
      $w(base64_encode($pass));
      $resp = $r();
      if (!$resp || substr($resp, 0, 3) !== '235') { fclose($conn); return false; }
    }

    $w("MAIL FROM:<$from>");
    $r();
    $w("RCPT TO:<$to>");
    $r();
    $w("DATA");
    $r();

    $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
    $msg .= "To: $to\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($body));
    $msg .= "\r\n.";
    $w($msg);
    $r();
    $w("QUIT");
    fclose($conn);
    return true;
  }
}
