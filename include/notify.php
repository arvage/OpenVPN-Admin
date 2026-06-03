<?php
// Called from OpenVPN connect/disconnect shell scripts:
//   php /var/www/openvpn-admin/include/notify.php connect|disconnect <common_name> [<trusted_ip>]
if (PHP_SAPI !== 'cli') exit(1);

$event  = $argv[1] ?? '';
$cn     = $argv[2] ?? 'unknown';
$ip     = $argv[3] ?? 'unknown';

if (!in_array($event, ['connect', 'disconnect', 'expiry'])) exit(1);

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/mailer.php');

$options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_SILENT;
try {
  $bdd = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass, $options);
} catch (Exception $e) { exit(1); }

$smtp_row = $bdd->query('SELECT * FROM smtp_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
if (!$smtp_row || empty($smtp_row['smtp_host'])) exit(0);

$notify_key = 'notify_' . $event;
if (empty($smtp_row[$notify_key])) exit(0);

// Get user email
$req = $bdd->prepare('SELECT user_mail FROM user WHERE user_id = ?');
$req->execute([$cn]);
$user_row = $req->fetch(PDO::FETCH_ASSOC);
$user_email = $user_row['user_mail'] ?? '';

if (empty($user_email)) exit(0);

$ts = date('Y-m-d H:i:s');
if ($event === 'connect') {
  $subject = "VPN Connected: $cn";
  $body    = "User $cn connected to VPN at $ts from $ip.";
} elseif ($event === 'disconnect') {
  $subject = "VPN Disconnected: $cn";
  $body    = "User $cn disconnected from VPN at $ts.";
} else {
  $subject = "VPN Account Expiring: $cn";
  $body    = "User $cn's VPN account is expiring soon.";
}

SmtpMailer::send($smtp_row, $user_email, $subject, $body);
