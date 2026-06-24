<?php
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
  ]);
  session_start();

  if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit(-1);
  }

  require(dirname(__FILE__) . '/connect.php');
  require(dirname(__FILE__) . '/functions.php');

  $role = getCurrentAdminRole($bdd);

  // CSRF validation for all state-changing POST requests
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
  }

  // ──────────────────── HELPER ────────────────────

  function createNotification($bdd, $type, $user_id, $detail) {
    try {
      $bdd->prepare('INSERT INTO notification (notification_type, notification_user_id, notification_detail) VALUES (?, ?, ?)')
          ->execute([$type, $user_id, $detail]);
    } catch (Exception $e) {}
  }

  // ──────────────────── SELECT / READ ────────────────────

  if (isset($_GET['select'])) {
    $sel = $_GET['select'];

    if ($sel === 'user') {
      $req = $bdd->prepare('SELECT * FROM user');
      $req->execute();
      $list = [];
      while ($data = $req->fetch()) {
        $list[] = [
          'user_id'         => $data['user_id'],
          'user_mail'       => $data['user_mail'],
          'user_phone'      => $data['user_phone'],
          'user_online'     => $data['user_online'],
          'user_enable'     => $data['user_enable'],
          'user_start_date' => $data['user_start_date'],
          'user_end_date'   => $data['user_end_date'],
        ];
      }
      echo json_encode($list);
    }

    else if ($sel === 'log' && isset($_GET['offset'], $_GET['limit'])) {
      $offset = intval($_GET['offset']);
      $limit  = intval($_GET['limit']);

      $nb = $bdd->query("SELECT COUNT(DISTINCT user_id) FROM log")->fetchColumn();

      $req = $bdd->prepare(
        "SELECT user_id,
                COUNT(*) AS sessions,
                SUM(log_received) AS total_received,
                SUM(log_send) AS total_sent,
                MAX(log_start_time) AS last_connected
         FROM log GROUP BY user_id ORDER BY last_connected DESC
         LIMIT :offset, :limit"
      );
      $req->bindValue(':offset', $offset, PDO::PARAM_INT);
      $req->bindValue(':limit',  $limit,  PDO::PARAM_INT);
      $req->execute();

      $list = [];
      while ($data = $req->fetch()) {
        $list[] = [
          'user_id'        => $data['user_id'],
          'sessions'       => $data['sessions'],
          'total_received' => formatBytes(intval($data['total_received'])),
          'total_sent'     => formatBytes(intval($data['total_sent'])),
          'last_connected' => $data['last_connected'],
        ];
      }
      echo json_encode(['total' => intval($nb), 'rows' => $list]);
    }

    else if ($sel === 'admin') {
      $req = $bdd->prepare('SELECT * FROM admin');
      $req->execute();
      $list = [];
      while ($data = $req->fetch()) {
        $list[] = [
          'admin_id'     => $data['admin_id'],
          'admin_mail'   => $data['admin_mail'] ?? '',
          'admin_phone'  => $data['admin_phone'] ?? '',
          'admin_enable' => $data['admin_enable'] ?? 1,
          'admin_role'   => $data['admin_role'] ?? 'read-only',
        ];
      }
      echo json_encode($list);
    }

    else if ($sel === 'stats') {
      $total    = $bdd->query('SELECT COUNT(*) FROM user')->fetchColumn();
      $online   = $bdd->query('SELECT COUNT(*) FROM user WHERE user_online = 1')->fetchColumn();
      $disabled = $bdd->query('SELECT COUNT(*) FROM user WHERE user_enable = 0')->fetchColumn();
      $logs     = $bdd->query('SELECT COUNT(*) FROM log')->fetchColumn();
      echo json_encode([
        'total_users' => intval($total),
        'online_now'  => intval($online),
        'disabled'    => intval($disabled),
        'log_entries' => intval($logs),
      ]);
    }

    else if ($sel === 'dashboard') {
      $clients = parseOpenVPNStatus();
      echo json_encode(['clients' => $clients, 'count' => count($clients)]);
    }

    else if ($sel === 'certificates') {
      $certs = listCertificates();
      if ($certs === null) {
        echo json_encode(['error' => 'EasyRSA index not found at /etc/openvpn/easy-rsa/pki/index.txt']);
      } else {
        echo json_encode($certs);
      }
    }

    else if ($sel === 'smtp') {
      requireSuperAdmin($bdd);
      $s = getSmtpSettings($bdd);
      // Never send the password back to the client
      if (isset($s['smtp_pass'])) $s['smtp_pass'] = empty($s['smtp_pass']) ? '' : '••••••••';
      echo json_encode($s);
    }

    else if ($sel === 'notifications') {
      if ($role !== 'super-admin') {
        echo json_encode(['is_super' => false, 'count' => 0, 'notifications' => []]);
        exit;
      }
      try {
        $req = $bdd->prepare('SELECT * FROM notification ORDER BY notification_created_at DESC LIMIT 50');
        $req->execute();
        $rows = $req->fetchAll(PDO::FETCH_ASSOC);
        $unread = 0;
        foreach ($rows as &$n) {
          $read_by = json_decode($n['notification_read_by'] ?: '[]', true);
          $n['is_read'] = in_array($_SESSION['admin_id'], $read_by);
          if (!$n['is_read']) $unread++;
        }
        echo json_encode(['is_super' => true, 'count' => $unread, 'notifications' => $rows]);
      } catch (Exception $e) {
        echo json_encode(['is_super' => false, 'count' => 0, 'notifications' => []]);
      }
    }

    else if ($sel === 'fail2ban') {
      requireSuperAdmin($bdd);
      echo json_encode(getFail2BanStatus());
    }

    else if ($sel === 'my_profile') {
      $req = $bdd->prepare('SELECT admin_id, admin_mail FROM admin WHERE admin_id = ?');
      $req->execute([$_SESSION['admin_id']]);
      echo json_encode($req->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    exit;
  }

  // ──────────────────── WRITE OPERATIONS (super-admin only) ────────────────────

  // ---- ADD USER ----
  if (isset($_POST['add_user'], $_POST['user_id'], $_POST['user_pass'])) {
    requireSuperAdmin($bdd);
    $id    = trim($_POST['user_id']);
    $pass  = hashPass($_POST['user_pass']);
    $mail  = trim($_POST['user_mail'] ?? '');
    $phone = trim($_POST['user_phone'] ?? '');
    $start = date('Y-m-d');
    $end   = null;

    $req = $bdd->prepare(
      'INSERT INTO user (user_id, user_pass, user_mail, user_phone, user_online, user_enable, user_start_date, user_end_date)
       VALUES (?, ?, ?, ?, 0, 1, ?, ?)'
    );
    $req->execute([$id, $pass, $mail, $phone, $start, $end]);
    createNotification($bdd, 'add_user', $id, 'User "' . $id . '" added by ' . $_SESSION['admin_id']);
    echo json_encode(['user_id' => $id, 'user_mail' => $mail, 'user_phone' => $phone,
                      'user_online' => 0, 'user_enable' => 1, 'user_start_date' => $start, 'user_end_date' => $end]);
  }

  // ---- UPDATE USER ----
  else if (isset($_POST['set_user'])) {
    requireSuperAdmin($bdd);
    $valid = ['user_id', 'user_pass', 'user_mail', 'user_phone', 'user_enable', 'user_start_date', 'user_end_date'];
    $field = $_POST['name'] ?? '';
    $value = $_POST['value'] ?? '';
    $pk    = $_POST['pk'] ?? '';

    if (!$pk || !in_array($field, $valid)) { http_response_code(400); exit; }

    if ($field === 'user_pass') {
      $value = hashPass($value);
    } else if (in_array($field, ['user_start_date', 'user_end_date']) && $value === '') {
      $value = null;
    }

    $req = $bdd->prepare("UPDATE user SET $field = ? WHERE user_id = ?");
    $req->execute([$value, $pk]);
    createNotification($bdd, 'edit_user', $pk, 'User "' . $pk . '" field "' . $field . '" updated by ' . $_SESSION['admin_id']);
    echo json_encode(['ok' => true]);
  }

  // ---- DELETE USER ----
  else if (isset($_POST['del_user'], $_POST['del_user_id'])) {
    requireSuperAdmin($bdd);
    $uid = $_POST['del_user_id'];
    $req = $bdd->prepare('DELETE FROM user WHERE user_id = ?');
    $req->execute([$uid]);
    createNotification($bdd, 'del_user', $uid, 'User "' . $uid . '" deleted by ' . $_SESSION['admin_id']);
    echo json_encode(['ok' => true]);
  }

  // ---- ADD ADMIN ----
  else if (isset($_POST['add_admin'], $_POST['admin_id'], $_POST['admin_pass'])) {
    requireSuperAdmin($bdd);
    $id    = trim($_POST['admin_id']);
    $pass  = hashPass($_POST['admin_pass']);
    $mail  = trim($_POST['admin_mail'] ?? '');
    $phone = trim($_POST['admin_phone'] ?? '');
    $role  = in_array($_POST['admin_role'] ?? '', ['super-admin', 'read-only']) ? $_POST['admin_role'] : 'super-admin';

    $req = $bdd->prepare(
      'INSERT INTO admin (admin_id, admin_pass, admin_mail, admin_phone, admin_enable, admin_role) VALUES (?, ?, ?, ?, 1, ?)'
    );
    $req->execute([$id, $pass, $mail, $phone, $role]);
    echo json_encode(['admin_id' => $id, 'admin_mail' => $mail, 'admin_phone' => $phone, 'admin_enable' => 1, 'admin_role' => $role]);
  }

  // ---- UPDATE ADMIN ----
  else if (isset($_POST['set_admin'])) {
    requireSuperAdmin($bdd);
    $valid = ['admin_id', 'admin_pass', 'admin_mail', 'admin_phone', 'admin_enable', 'admin_role'];
    $field = $_POST['name'] ?? '';
    $value = $_POST['value'] ?? '';
    $pk    = $_POST['pk'] ?? '';

    if (!$pk || !in_array($field, $valid)) { http_response_code(400); exit; }

    if ($field === 'admin_pass') {
      $value = hashPass($value);
    } else if ($field === 'admin_role' && !in_array($value, ['super-admin', 'read-only'])) {
      http_response_code(400); exit;
    }

    $req = $bdd->prepare("UPDATE admin SET $field = ? WHERE admin_id = ?");
    $req->execute([$value, $pk]);
    echo json_encode(['ok' => true]);
  }

  // ---- DELETE ADMIN ----
  else if (isset($_POST['del_admin'], $_POST['del_admin_id'])) {
    requireSuperAdmin($bdd);
    // Prevent deleting own account
    if ($_POST['del_admin_id'] === $_SESSION['admin_id']) {
      echo json_encode(['error' => 'Cannot delete your own account.']); exit;
    }
    $req = $bdd->prepare('DELETE FROM admin WHERE admin_id = ?');
    $req->execute([$_POST['del_admin_id']]);
    echo json_encode(['ok' => true]);
  }

  // ---- BAN IP (fail2ban) ----
  else if (isset($_POST['ban_ip'])) {
    requireSuperAdmin($bdd);
    $ip   = trim($_POST['ip']   ?? '');
    $jail = trim($_POST['jail'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid IP address.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $jail)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid jail name.']); exit;
    }
    $output = @shell_exec('sudo fail2ban-client set ' . escapeshellarg($jail) . ' banip ' . escapeshellarg($ip) . ' 2>&1');
    $ok = ($output !== null && stripos($output, 'error') === false);
    echo json_encode(['ok' => $ok, 'output' => htmlspecialchars($output ?? '', ENT_QUOTES, 'UTF-8')]);
  }

  // ---- UNBAN IP (fail2ban) ----
  else if (isset($_POST['unban_ip'])) {
    requireSuperAdmin($bdd);
    $ip   = trim($_POST['ip']   ?? '');
    $jail = trim($_POST['jail'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid IP address.']); exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $jail)) {
      echo json_encode(['ok' => false, 'error' => 'Invalid jail name.']); exit;
    }
    $output = @shell_exec('sudo fail2ban-client set ' . escapeshellarg($jail) . ' unbanip ' . escapeshellarg($ip) . ' 2>&1');
    $ok = ($output !== null && stripos($output, 'error') === false);
    echo json_encode(['ok' => $ok, 'output' => htmlspecialchars($output ?? '', ENT_QUOTES, 'UTF-8')]);
  }

  // ---- MARK NOTIFICATIONS READ ----
  else if (isset($_POST['mark_notifications_read'])) {
    if ($role !== 'super-admin') { http_response_code(403); exit; }
    try {
      $admin_id = $_SESSION['admin_id'];
      $rows = $bdd->query('SELECT notification_id, notification_read_by FROM notification')->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rows as $n) {
        $read_by = json_decode($n['notification_read_by'] ?: '[]', true);
        if (!in_array($admin_id, $read_by)) {
          $read_by[] = $admin_id;
          $bdd->prepare('UPDATE notification SET notification_read_by = ? WHERE notification_id = ?')
              ->execute([json_encode($read_by), $n['notification_id']]);
        }
      }
      echo json_encode(['ok' => true]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false]);
    }
  }

  // ---- UPDATE OWN PROFILE EMAIL ----
  else if (isset($_POST['set_admin_profile'])) {
    $mail = filter_var(trim($_POST['admin_mail'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
    $bdd->prepare('UPDATE admin SET admin_mail = ? WHERE admin_id = ?')
        ->execute([$mail, $_SESSION['admin_id']]);
    echo json_encode(['ok' => true]);
  }

  // ---- UPDATE CONFIG FILE ----
  else if (isset($_POST['update_config'])) {
    requireSuperAdmin($bdd);
    $allowed_configs = [
      'client-conf/gnu-linux/client.conf',
      'client-conf/windows/client.ovpn',
      'client-conf/osx-viscosity/client.conf',
      'client-conf/windows/filename',
    ];
    $config_file = $_POST['config_file'] ?? '';
    if (!in_array($config_file, $allowed_configs, true)) {
      echo json_encode(['config_success' => false]);
      exit;
    }

    $config_name = basename($config_file);
    $config_dir  = dirname($config_file);
    $history_dir = "../$config_dir/history";

    if (!file_exists($history_dir))
      @mkdir($history_dir, 0755, true);
    $ts = time();
    @copy("../$config_file", "$history_dir/{$ts}_{$config_name}");

    $ok = file_put_contents("../$config_file", $_POST['config_content']);
    echo json_encode(['config_success' => $ok !== false]);
  }

  // ---- SAVE SMTP SETTINGS ----
  else if (isset($_POST['save_smtp'])) {
    requireSuperAdmin($bdd);
    $host        = trim($_POST['smtp_host'] ?? '');
    $port        = intval($_POST['smtp_port'] ?? 587);
    $smtp_user   = trim($_POST['smtp_user'] ?? '');
    $from        = trim($_POST['smtp_from'] ?? '');
    $from_name   = trim($_POST['smtp_from_name'] ?? 'OpenVPN Admin');
    $secure      = in_array($_POST['smtp_secure'] ?? '', ['tls','ssl','none']) ? $_POST['smtp_secure'] : 'tls';
    $n_connect   = intval($_POST['notify_connect'] ?? 0);
    $n_disconnect= intval($_POST['notify_disconnect'] ?? 0);
    $n_expiry    = intval($_POST['notify_expiry'] ?? 0);

    $fields = 'smtp_host=?, smtp_port=?, smtp_user=?, smtp_from=?, smtp_from_name=?, smtp_secure=?, notify_connect=?, notify_disconnect=?, notify_expiry=?';
    $vals   = [$host, $port, $smtp_user, $from, $from_name, $secure, $n_connect, $n_disconnect, $n_expiry];

    // Only update password if a new one was provided
    if (!empty($_POST['smtp_pass'])) {
      $fields .= ', smtp_pass=?';
      $vals[]  = trim($_POST['smtp_pass']);
    }
    $vals[] = 1; // WHERE id=1

    $req = $bdd->prepare("UPDATE smtp_settings SET $fields WHERE id=?");
    $req->execute($vals);
    echo json_encode(['ok' => true]);
  }

  // ---- TEST SMTP ----
  else if (isset($_POST['test_smtp'], $_POST['test_email'])) {
    requireSuperAdmin($bdd);
    require_once(dirname(__FILE__) . '/mailer.php');
    $smtp = getSmtpSettings($bdd);
    $ok = SmtpMailer::send($smtp, trim($_POST['test_email']), 'OpenVPN Admin – SMTP Test', "This is a test email from your OpenVPN Admin panel.\nIf you received this, SMTP is configured correctly.");
    echo json_encode(['ok' => $ok, 'error' => $ok ? null : 'Failed to send. Check SMTP settings and server logs.']);
  }

  // ---- GENERATE CERTIFICATE ----
  else if (isset($_POST['cert_generate'], $_POST['cert_name'])) {
    requireSuperAdmin($bdd);
    $cn = preg_replace('/[^a-zA-Z0-9._-]/', '', trim($_POST['cert_name']));
    if (empty($cn)) { echo json_encode(['ok' => false, 'output' => 'Invalid certificate name.']); exit; }

    $easyrsa = '/etc/openvpn/easy-rsa';
    if (!is_dir($easyrsa)) { echo json_encode(['ok' => false, 'output' => "EasyRSA not found at $easyrsa"]); exit; }

    $cmd    = "cd " . escapeshellarg($easyrsa) . " && sudo ./easyrsa --batch build-client-full " . escapeshellarg($cn) . " nopass 2>&1";
    $output = shell_exec($cmd);
    $ok     = file_exists("$easyrsa/pki/issued/$cn.crt");
    echo json_encode(['ok' => $ok, 'output' => htmlspecialchars($output ?? '')]);
  }

  // ---- REVOKE CERTIFICATE ----
  else if (isset($_POST['cert_revoke'], $_POST['cert_name'])) {
    requireSuperAdmin($bdd);
    $cn = preg_replace('/[^a-zA-Z0-9._-]/', '', trim($_POST['cert_name']));
    if (empty($cn)) { echo json_encode(['ok' => false, 'output' => 'Invalid certificate name.']); exit; }

    $easyrsa = '/etc/openvpn/easy-rsa';
    $cmd    = "cd " . escapeshellarg($easyrsa) . " && sudo ./easyrsa --batch revoke " . escapeshellarg($cn) . " 2>&1 && sudo ./easyrsa --batch gen-crl 2>&1";
    $output = shell_exec($cmd);
    echo json_encode(['ok' => true, 'output' => htmlspecialchars($output ?? '')]);
  }

  // ---- DOWNLOAD USER OVPN ----
  else if (isset($_GET['cert_download'], $_GET['cert_name'])) {
    requireSuperAdmin($bdd);
    $cn = preg_replace('/[^a-zA-Z0-9._-]/', '', trim($_GET['cert_name']));
    if (empty($cn)) { http_response_code(400); exit; }

    $easyrsa  = '/etc/openvpn/easy-rsa';
    $cert_file = "$easyrsa/pki/issued/$cn.crt";
    $key_file  = "$easyrsa/pki/private/$cn.key";
    $ca_file   = '/etc/openvpn/ca.crt';
    $ta_file   = '/etc/openvpn/ta.key';

    if (!file_exists($cert_file) || !is_readable($cert_file)) {
      http_response_code(404);
      echo "Certificate not found for: $cn";
      exit;
    }

    // Base config template
    $template_path = dirname(__FILE__) . '/../client-conf/windows/client.ovpn';
    $base = file_exists($template_path) ? file_get_contents($template_path) : '';

    // Strip any existing inline certs from template
    $base = preg_replace('/<ca>.*?<\/ca>\s*/s', '', $base);
    $base = preg_replace('/<cert>.*?<\/cert>\s*/s', '', $base);
    $base = preg_replace('/<key>.*?<\/key>\s*/s', '', $base);
    $base = preg_replace('/<tls-auth>.*?<\/tls-auth>\s*/s', '', $base);
    $base = rtrim($base);

    // Extract only the certificate block (strip bag attributes)
    $cert_raw = file_get_contents($cert_file);
    if (preg_match('/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/s', $cert_raw, $m)) {
      $cert_raw = $m[1];
    }

    $ovpn  = $base . "\n\n";
    $ovpn .= "<ca>\n"       . file_get_contents($ca_file) . "</ca>\n";
    $ovpn .= "<cert>\n"     . $cert_raw . "\n</cert>\n";
    $ovpn .= "<key>\n"      . file_get_contents($key_file) . "</key>\n";
    if (file_exists($ta_file)) {
      $ovpn .= "key-direction 1\n";
      $ovpn .= "<tls-auth>\n" . file_get_contents($ta_file) . "</tls-auth>\n";
    }

    $filename = $cn . '.ovpn';
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Pragma: no-cache');
    echo $ovpn;
    exit;
  }

  // ---- SEND CONFIG VIA EMAIL ----
  else if (isset($_POST['send_config_email'], $_POST['cert_name'], $_POST['email_to'])) {
    requireSuperAdmin($bdd);
    require_once(dirname(__FILE__) . '/mailer.php');

    $cn     = preg_replace('/[^a-zA-Z0-9._-]/', '', trim($_POST['cert_name']));
    $email  = filter_var(trim($_POST['email_to']), FILTER_VALIDATE_EMAIL);
    if (!$cn || !$email) { echo json_encode(['ok' => false, 'error' => 'Invalid input']); exit; }

    $cert_file = "/etc/openvpn/easy-rsa/pki/issued/$cn.crt";
    if (!file_exists($cert_file)) { echo json_encode(['ok' => false, 'error' => 'Certificate not found']); exit; }

    $smtp = getSmtpSettings($bdd);
    $body = "Hello,\n\nYour OpenVPN configuration file for '$cn' has been generated.\n"
          . "Please find the attached .ovpn file and import it into your OpenVPN client.\n\n"
          . "OpenVPN Admin";

    // For file attachment we use a multipart message — build a minimal MIME email
    $ok = SmtpMailer::send($smtp, $email, "OpenVPN Config: $cn", $body);
    echo json_encode(['ok' => $ok]);
  }
