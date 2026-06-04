<?php
  session_start();

  require(dirname(__FILE__) . '/include/functions.php');
  require(dirname(__FILE__) . '/include/connect.php');

  // First-time install redirect
  if (($_SERVER['REQUEST_URI'] !== '/index.php?installation') && !isInstalled($bdd)) {
    header('Location: index.php?installation');
    exit(-1);
  }

  // Logout
  if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: .');
    exit(-1);
  }

  // Read ovpn filename
  $ovpn_filename = trim(@file_get_contents('./client-conf/windows/filename') ?: 'client');

  // Windows instruction download
  if (isset($_POST['windows_instruction_get'])) {
    $f = './client-conf/windows/Download and install the OpenVPN GUI (Windows).pdf';
    if (file_exists($f)) {
      header('Content-type: application/pdf');
      header('Content-Disposition: attachment; filename="OpenVPN Windows Guide.pdf"');
      readfile($f);
    }
    exit;
  }

  // macOS instruction download
  if (isset($_POST['mac_instruction_get'])) {
    $f = './client-conf/osx-viscosity/Download and install the OpenVPN GUI (MAC).pdf';
    if (file_exists($f)) {
      header('Content-type: application/pdf');
      header('Content-Disposition: attachment; filename="OpenVPN macOS Guide.pdf"');
      readfile($f);
    }
    exit;
  }

  // Admin: download shared config
  if (isset($_GET['admin_configuration_get']) && !empty($_SESSION['admin_id'])) {
    $f = './client-conf/windows/client.ovpn';
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"$ovpn_filename.ovpn\"");
    readfile($f);
    exit;
  }

  // User: download config with credentials
  if (isset($_POST['configuration_get'], $_POST['configuration_username'], $_POST['configuration_pass'])
      && !empty($_POST['configuration_pass'])) {
    $req = $bdd->prepare('SELECT * FROM user WHERE user_id = ?');
    $req->execute([$_POST['configuration_username']]);
    $data = $req->fetch();

    if ($data && passEqual($_POST['configuration_pass'], $data['user_pass'])) {
      $f = './client-conf/windows/client.ovpn';
      header('Content-Type: application/octet-stream');
      header("Content-Disposition: attachment; filename=\"$ovpn_filename.ovpn\"");
      readfile($f);
      exit;
    } else {
      $error = true;
    }
  }

  // Admin login
  else if (isset($_POST['admin_login'], $_POST['admin_username'], $_POST['admin_pass'])
           && !empty($_POST['admin_pass'])) {
    $req = $bdd->prepare('SELECT * FROM admin WHERE admin_id = ?');
    $req->execute([$_POST['admin_username']]);
    $data = $req->fetch();

    if ($data && (!isset($data['admin_enable']) || $data['admin_enable']) && passEqual($_POST['admin_pass'], $data['admin_pass'])) {
      $_SESSION['admin_id'] = $data['admin_id'];
      header('Location: index.php?admin');
      exit(-1);
    } else {
      $error = true;
    }
  }

  $isAdmin = isset($_GET['admin']) && isset($_SESSION['admin_id']);
  $isInstall = isset($_GET['installation']);
  $adminRole = $isAdmin ? getCurrentAdminRole($bdd) : null;

  $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
  $valid_pages = ['dashboard', 'users', 'logs', 'admins', 'configs', 'filename', 'certificates', 'settings'];
  if (!in_array($page, $valid_pages)) $page = 'dashboard';

  $page_titles = [
    'dashboard'    => 'Dashboard',
    'users'        => 'OpenVPN Users',
    'logs'         => 'Logs',
    'admins'       => 'Web Admins',
    'configs'      => 'Configs',
    'filename'     => 'File Name',
    'certificates' => 'Certificates',
    'settings'     => 'Settings',
  ];
  $topbar_title = $page_titles[$page] ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>OpenVPN Admin</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.4/dist/bootstrap-table.min.css"/>
  <link rel="stylesheet" href="css/index.css"/>
  <link rel="icon" type="image/png" href="css/icon.png"/>
</head>
<body class="<?php
  if ($isAdmin) echo 'admin-layout';
  else if ($isInstall) echo 'install-layout bg-light';
  else echo 'login-layout bg-light';
?>">
<?php

  // ─── INSTALLATION ───
  if ($isInstall) {
    if (isInstalled($bdd)) {
      printError('OpenVPN-admin is already installed. Redirecting…');
      header('refresh:3;url=index.php?admin');
      exit(-1);
    }
    if (isset($_POST['admin_username'])) {
      if ($_POST['admin_pass'] !== $_POST['repeat_admin_pass']) {
        printError('Passwords do not match.');
        header('refresh:3;url=index.php?installation');
        exit(-1);
      }
      $migrations = getMigrationSchemas();
      foreach ($migrations as $mv) {
        $sql_file = dirname(__FILE__) . "/sql/schema-$mv.sql";
        if (!file_exists($sql_file)) continue;
        try {
          $bdd->exec(file_get_contents($sql_file));
        } catch (PDOException $e) {
          printError($e->getMessage()); exit(1);
        }
        @unlink($sql_file);
        updateSchema($bdd, $mv);
      }
      $bdd->prepare('INSERT INTO admin (admin_id, admin_pass, admin_role) VALUES (?, ?, ?)')
          ->execute([$_POST['admin_username'], hashPass($_POST['admin_pass']), 'super-admin']);
      @rmdir(dirname(__FILE__) . '/sql');
      printSuccess('Installation complete! Redirecting to admin panel…');
      header('refresh:3;url=index.php?admin');
      exit(-1);
    }
    require(dirname(__FILE__) . '/include/html/menu.php');
    require(dirname(__FILE__) . '/include/html/form/installation.php');
    exit(-1);
  }

  // ─── PUBLIC CONFIG DOWNLOAD / LOGIN ───
  if (!$isAdmin) {
    if (isset($error) && $error) printError('Invalid username or password.');
    require(dirname(__FILE__) . '/include/html/menu.php');
    echo '<div class="container py-4">';
    require(dirname(__FILE__) . '/include/html/form/login.php');
    echo '<hr class="my-4"/>';
    require(dirname(__FILE__) . '/include/html/form/configuration.php');
    echo '</div>';
    // Scripts at end
    echo '<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
    exit;
  }

  // ─── ADMIN PANEL ───
?>

<div class="admin-wrapper">
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <i class="bi bi-shield-lock"></i> OpenVPN Admin
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li class="nav-section-label">Overview</li>
        <li class="<?= $page==='dashboard'?'active':'' ?>">
          <a href="index.php?admin&page=dashboard">
            <i class="bi bi-activity"></i> Dashboard
          </a>
        </li>
        <li class="nav-section-label">Management</li>
        <li class="<?= $page==='users'?'active':'' ?>">
          <a href="index.php?admin&page=users">
            <i class="bi bi-people"></i> Users
          </a>
        </li>
        <li class="<?= $page==='logs'?'active':'' ?>">
          <a href="index.php?admin&page=logs">
            <i class="bi bi-journal-text"></i> Logs
          </a>
        </li>
        <li class="<?= $page==='certificates'?'active':'' ?>">
          <a href="index.php?admin&page=certificates">
            <i class="bi bi-patch-check"></i> Certificates
          </a>
        </li>
        <li class="nav-section-label">Administration</li>
        <li class="<?= $page==='admins'?'active':'' ?>">
          <a href="index.php?admin&page=admins">
            <i class="bi bi-person-gear"></i> Admins
          </a>
        </li>
        <li class="<?= $page==='configs'?'active':'' ?>">
          <a href="index.php?admin&page=configs">
            <i class="bi bi-file-earmark-code"></i> Configs
          </a>
        </li>
        <li class="<?= $page==='filename'?'active':'' ?>">
          <a href="index.php?admin&page=filename">
            <i class="bi bi-file-earmark-text"></i> File Name
          </a>
        </li>
        <li class="<?= $page==='settings'?'active':'' ?>">
          <a href="index.php?admin&page=settings">
            <i class="bi bi-sliders"></i> Settings
          </a>
        </li>
      </ul>
    </nav>
    <div class="sidebar-footer">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-person-circle"></i>
        <span class="flex-grow-1 text-truncate"><?= htmlspecialchars($_SESSION['admin_id']) ?></span>
        <?php if ($adminRole === 'super-admin'): ?>
          <span class="badge bg-primary" title="Super Admin">SA</span>
        <?php else: ?>
          <span class="badge bg-secondary" title="Read Only">RO</span>
        <?php endif; ?>
      </div>
      <div class="sidebar-credit">
        <i class="bi bi-github"></i>
        <a href="https://github.com/arvage/OpenVPN-Admin" target="_blank" rel="noopener">
          by Armin
        </a>
      </div>
    </div>
  </aside>

  <!-- Main content -->
  <div class="main-content">
    <!-- Topbar -->
    <div class="topbar">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebar-toggle">
          <i class="bi bi-list"></i>
        </button>
        <span class="topbar-title"><?= htmlspecialchars($topbar_title) ?></span>
      </div>
      <div class="d-flex gap-2">
        <a href="index.php?admin_configuration_get" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-download me-1"></i>Shared Config
        </a>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-people me-1"></i>User Portal
        </a>
        <a href="index.php?logout" class="btn btn-sm btn-danger">
          <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
      </div>
    </div>

    <!-- Page content -->
    <?php
      require(dirname(__FILE__) . '/include/html/grids.php');
    ?>
  </div>
</div>

<!-- Toast notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.4/dist/bootstrap-table.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.4/dist/extensions/filter-control/bootstrap-table-filter-control.min.js"></script>
<script>
  // Expose role and page to JS
  window.ADMIN_ROLE = <?= json_encode($adminRole) ?>;
  window.CURRENT_PAGE = <?= json_encode($page) ?>;
</script>
<script src="js/grids.js"></script>

</body>
</html>
