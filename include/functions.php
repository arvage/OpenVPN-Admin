<?php

  function getMigrationSchemas() {
    return [ 0, 5, 10 ];
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
    $req = $bdd->prepare("SHOW TABLES LIKE 'admin'");
    $req->execute();
    return (bool)$req->fetch();
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
      return ($data && !empty($data['admin_role'])) ? $data['admin_role'] : 'super-admin';
    } catch (Exception $e) {
      return 'super-admin';
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
        'cn'          => $cn,
        'has_cert'    => file_exists($cert_path),
      ];
    }
    return $certs;
  }

  function getSmtpSettings($bdd): array {
    try {
      $req = $bdd->query('SELECT * FROM smtp_settings WHERE id = 1');
      return $req->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
      return [];
    }
  }
