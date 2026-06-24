<?php

  function getMigrationSchemas() {
    return [ 0, 5, 10, 11 ];
  }

  function updateSchema($bdd, $newKey) {
    if ($newKey === 0) {
      $req_string = 'INSERT INTO `application` (sql_schema) VALUES (?)';
    } else {
      $req_string = 'UPDATE `application` SET `sql_schema` = ?';
    }
    $req = $bdd->prepare($req_string);
    $req->execute([$newKey]);
  }

  function printError($str) {
    echo '<div class="alert alert-danger" role="alert">' . $str . '</div>';
  }

  function printSuccess($str) {
    echo '<div class="alert alert-success" role="alert">' . $str . '</div>';
  }

  function isInstalled($bdd) {
    try {
      $req = $bdd->query("SELECT COUNT(*) FROM admin");
      return (int)$req->fetchColumn() > 0;
    } catch (Exception $e) {
      return false;
    }
  }

  function hashPass($pass) {
    return password_hash($pass, PASSWORD_DEFAULT);
  }

  function passEqual($pass, $hash) {
    return password_verify($pass, $hash);
  }

  function getCurrentAdminRole($bdd) {
    if (empty($_SESSION['admin_id'])) return null;
    try {
      $req = $bdd->prepare('SELECT admin_role FROM admin WHERE admin_id = ?');
      $req->execute([$_SESSION['admin_id']]);
      $data = $req->fetch();
      // Fail closed: unknown or missing role → least privilege
      return ($data && !empty($data['admin_role'])) ? $data['admin_role'] : 'read-only';
    } catch (Exception $e) {
      return 'read-only';
    }
  }

  function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }

  function verifyCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
      http_response_code(403);
      echo json_encode(['error' => 'Invalid CSRF token.']);
      exit;
    }
  }

  function requireSuperAdmin($bdd) {
    if (getCurrentAdminRole($bdd) !== 'super-admin') {
      http_response_code(403);
      echo json_encode(['error' => 'Insufficient permissions. Super-admin role required.']);
      exit(-1);
    }
  }

  function formatBytes(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1)   . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024)         . ' KB';
    return $bytes . ' B';
  }

  function parseOpenVPNStatus(): array {
    $paths = [
      '/etc/openvpn/server/openvpn-status.log',
      '/etc/openvpn/openvpn-status.log',
      '/var/log/openvpn/openvpn-status.log',
      '/tmp/openvpn-status.log',
    ];
    $status_file = null;
    foreach ($paths as $p) {
      if (file_exists($p) && is_readable($p)) { $status_file = $p; break; }
    }
    if (!$status_file) return [];

    $lines = file($status_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $clients = [];
    $in_clients = false;

    foreach ($lines as $line) {
      if (preg_match('/^Common Name[,\t]Real Address/', $line)) { $in_clients = true; continue; }
      if (preg_match('/^ROUTING TABLE|^GLOBAL STATS|^OpenVPN STATISTICS/', $line)) { $in_clients = false; continue; }
      if (!$in_clients) continue;

      $parts = (strpos($line, "\t") !== false) ? explode("\t", $line) : explode(",", $line);
      if (count($parts) < 4) continue;

      $rx = intval($parts[2] ?? 0);
      $tx = intval($parts[3] ?? 0);
      $clients[] = [
        'common_name'    => htmlspecialchars(trim($parts[0])),
        'real_address'   => htmlspecialchars(trim($parts[1])),
        'bytes_received' => $rx,
        'bytes_sent'     => $tx,
        'rx_human'       => formatBytes($rx),
        'tx_human'       => formatBytes($tx),
        'connected_since'=> htmlspecialchars(trim($parts[4] ?? '')),
      ];
    }
    return $clients;
  }

  function listCertificates(): ?array {
    $index_file = '/etc/openvpn/easy-rsa/pki/index.txt';
    if (!file_exists($index_file) || !is_readable($index_file)) return null;

    $lines = file($index_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $certs = [];
    foreach ($lines as $line) {
      $parts = preg_split('/\t/', $line);
      if (count($parts) < 5) continue;

      $status   = trim($parts[0]);
      $cn_field = trim(end($parts));

      if (preg_match('/CN=([^\/\n\r]+)/', $cn_field, $m)) {
        $cn = trim($m[1]);
      } else {
        $cn = $cn_field;
      }

      if (in_array(strtolower($cn), ['server', 'ca', 'easy-rsa ca'])) continue;

      $cert_path = "/etc/openvpn/easy-rsa/pki/issued/$cn.crt";
      $certs[] = [
        'status'      => $status,
        'status_label'=> isset(['V'=>'Valid','R'=>'Revoked','E'=>'Expired'][$status]) ? ['V'=>'Valid','R'=>'Revoked','E'=>'Expired'][$status] : $status,
        'cn'          => htmlspecialchars($cn, ENT_QUOTES, 'UTF-8'),
        'has_cert'    => file_exists($cert_path),
      ];
    }
    return $certs;
  }

  // Execute a SQL migration file statement-by-statement.
  // Silently skips "duplicate column" (1060) and "table already exists" (1050)
  // so migrations are safe to re-run and work on both MySQL and MariaDB.
  function execMigrationSql($bdd, $sql) {
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
      if ($stmt === '') continue;
      try {
        $bdd->exec($stmt);
      } catch (PDOException $e) {
        $errno = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
        // 1060 = Duplicate column name, 1050 = Table already exists
        if (!in_array($errno, [1060, 1050])) {
          throw $e;
        }
      }
    }
  }

  function getFail2BanStatus(): array {
    $out = @shell_exec('sudo fail2ban-client status 2>&1');
    if ($out === null || trim($out) === '') {
      return ['error' => 'fail2ban-client unavailable. Ensure fail2ban is installed and the web server has passwordless sudo access to fail2ban-client.'];
    }
    if (preg_match('/command not found|No such file/i', $out)) {
      return ['error' => 'fail2ban-client not found. Install it: sudo apt install fail2ban'];
    }
    if (preg_match('/Connection refused|not running|Failed to connect/i', $out)) {
      return ['error' => 'fail2ban is not running. Start it: sudo systemctl start fail2ban'];
    }

    if (!preg_match('/Jail list:\s*(.+)/i', $out, $m)) {
      if (preg_match('/Number of jail:\s*0/i', $out)) {
        return ['jails' => [], 'total_banned' => 0];
      }
      return ['error' => 'Could not parse fail2ban output.', 'raw' => htmlspecialchars($out, ENT_QUOTES, 'UTF-8')];
    }

    $jail_names = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    $jails = [];
    $total_banned = 0;

    foreach ($jail_names as $jail) {
      if (!preg_match('/^[a-zA-Z0-9_-]+$/', $jail)) continue;
      $jout = @shell_exec('sudo fail2ban-client status ' . escapeshellarg($jail) . ' 2>&1');

      $banned_now = 0; $total_fail = 0; $failed_now = 0; $banned_ips = [];
      if (preg_match('/Currently banned:\s*(\d+)/i',  $jout, $mm)) $banned_now = intval($mm[1]);
      if (preg_match('/Total banned:\s*(\d+)/i',      $jout, $mm)) $total_fail = intval($mm[1]);
      if (preg_match('/Currently failed:\s*(\d+)/i',  $jout, $mm)) $failed_now = intval($mm[1]);
      if (preg_match('/Banned IP list:\s*([^\n]+)/i', $jout, $mm)) {
        $banned_ips = array_values(array_filter(preg_split('/\s+/', trim($mm[1]))));
      }
      $total_banned += $banned_now;
      $jails[] = [
        'jail'              => $jail,
        'currently_banned'  => $banned_now,
        'total_banned'      => $total_fail,
        'currently_failed'  => $failed_now,
        'banned_ips'        => $banned_ips,
      ];
    }

    return ['jails' => $jails, 'total_banned' => $total_banned];
  }

  function getSmtpSettings($bdd): array {
    try {
      $req = $bdd->query('SELECT * FROM smtp_settings WHERE id = 1');
      return $req->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
      return [];
    }
  }
