<?php
  session_start();

  require(dirname(__FILE__) . '/include/functions.php');
  require(dirname(__FILE__) . '/include/connect.php');

  // first time install
  if(($_SERVER[REQUEST_URI] != "/index.php?installation") && (isInstalled($bdd) == false)) {
    header("Location: index.php?installation");
    exit(-1);
  }
  
  // Disconnecting ?
  if(isset($_GET['logout'])){
    session_destroy();
    header("Location: .");
    exit(-1);
  }

  // Read ovpn file contents
  $ovpn_filename= file_get_contents("./client-conf/windows/filename");

  // Get the Windows instruction file 
  if(isset($_POST['windows_instruction_get'])) {
      $download_file_name1 = "Download and install the OpenVPN GUI (Windows).pdf";
      $file_folder1  = "windows";
      $file_full_path1  = './client-conf/' . $file_folder1 . '/' . $download_file_name1;
      header("Content-type: application/pdf");
      header("Content-disposition: attachment; filename=$download_file_name1");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile($file_full_path1);
      exit;
     }

  // Get the MAC instruction file 
  if(isset($_POST['mac_instruction_get'])) {
    
      $download_file_name2 = "Download and install the OpenVPN GUI (MAC).pdf";
      $file_folder2  = "osx-viscosity";
      $file_full_path2  = './client-conf/' . $file_folder2 . '/' . $download_file_name2;
      header("Content-type: application/pdf");
      header("Content-disposition: attachment; filename=$download_file_name2");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile($file_full_path2);
      exit;
    }

  // Get configuration file from admin page
  if(isset($_GET['admin_configuration_get'])  && !empty($_SESSION['admin_id']) ) {
    $file_name = "client.ovpn";
    $file_folder  = "windows";
    $file_full_path  = './client-conf/' . $file_folder . '/' . $file_name;
    header("Content-type: application/ovpn");
    header("Content-disposition: attachment; filename=$ovpn_filename.ovpn");
    header("Pragma: no-cache");
    header("Expires: 0");
    readfile($file_full_path);
    exit;
  }

  // Get the configuration files from configuration page
  if(isset($_POST['configuration_get'], $_POST['configuration_username'], $_POST['configuration_pass']) && !empty($_POST['configuration_pass'])) {
    $req = $bdd->prepare('SELECT * FROM user WHERE user_id = ?');
    $req->execute(array($_POST['configuration_username']));
    $data = $req->fetch();

    // Error ?
    if($data && passEqual($_POST['configuration_pass'], $data['user_pass'])) {
      $file_name = "client.ovpn";
      $file_folder  = "windows";
      $file_full_path  = './client-conf/' . $file_folder . '/' . $file_name;
      header("Content-type: application/ovpn");
      header("Content-disposition: attachment; filename=$ovpn_filename.ovpn");
      header("Pragma: no-cache");
      header("Expires: 0");
      readfile($file_full_path);
      exit;
    }
    else {
      $error = true;
    }
  }

  // Admin login attempt ?
  else if(isset($_POST['admin_login'], $_POST['admin_username'], $_POST['admin_pass']) && !empty($_POST['admin_pass'])){

    $req = $bdd->prepare('SELECT * FROM admin WHERE admin_id = ?');
    $req->execute(array($_POST['admin_username']));
    $data = $req->fetch();

    // Error ?
    if($data && passEqual($_POST['admin_pass'], $data['admin_pass'])) {
      $_SESSION['admin_id'] = $data['admin_id'];
      header("Location: index.php?admin");
      exit(-1);
    }
    else {
      $error = true;
    }
  }
?>

<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />

    <title>OpenVPN-Admin</title>

    <link rel="stylesheet" href="vendor/bootstrap/dist/css/bootstrap.min.css" type="text/css" />
    <link rel="stylesheet" href="vendor/x-editable/dist/bootstrap3-editable/css/bootstrap-editable.css" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap-table/dist/bootstrap-table.min.css" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap-table/dist/extensions/filter-control/bootstrap-table-filter-control.css" type="text/css" />
    <link rel="stylesheet" href="css/index.css" type="text/css" />

    <link rel="icon" type="image/png" href="css/icon.png">
  </head>
  <body class='container-fluid<?php if(!isset($_GET['installation']) && (!isset($_GET['admin']) || !isset($_SESSION['admin_id']))) echo ' unified-login-body'; ?>'>
  <?php

    // --------------- INSTALLATION ---------------
    if(isset($_GET['installation'])) {
      if(isInstalled($bdd) == true) {
        printError('OpenVPN-admin is already installed. Redirection.');
        header( "refresh:3;url=index.php?admin" );
        exit(-1);
      }

      // If the user sent the installation form
      if(isset($_POST['admin_username'])) {
        $admin_username = $_POST['admin_username'];
        $admin_pass = $_POST['admin_pass'];
        $admin_repeat_pass = $_POST['repeat_admin_pass'];

        if($admin_pass != $admin_repeat_pass) {
          printError('The passwords do not correspond. Redirection.');
          header( "refresh:3;url=index.php?installation" );
          exit(-1);
        }

        // Create the initial tables
        $migrations = getMigrationSchemas();
        foreach ($migrations as $migration_value) {
          $sql_file = dirname(__FILE__) . "/sql/schema-$migration_value.sql";
          try {
            $sql = file_get_contents($sql_file);
            $bdd->exec($sql);
          }
          catch (PDOException $e) {
            printError($e->getMessage());
            exit(1);
          }

          unlink($sql_file);

          // Update schema to the new value
          updateSchema($bdd, $migration_value);
        }

        // Generate the hash
        $hash_pass = hashPass($admin_pass);

        // Insert the new admin
        $req = $bdd->prepare('INSERT INTO admin (admin_id, admin_pass) VALUES (?, ?)');
        $req->execute(array($admin_username, $hash_pass));

        rmdir(dirname(__FILE__) . '/sql');
        printSuccess('Well done, OpenVPN-Admin is installed. Redirection.');
        header( "refresh:3;url=index.php?admin" );
      }
      // Print the installation form
      else {    
        require(dirname(__FILE__) . '/include/html/menu.php');
        require(dirname(__FILE__) . '/include/html/form/installation.php');
      }
      exit(-1);
    }

    // --------------- UNIFIED LOGIN / CONFIG ---------------
    if(!isset($_GET['admin']) || !isset($_SESSION['admin_id'])) {
      if(isset($error) && $error == true)
        printError('Login error');

      require(dirname(__FILE__) . '/include/html/form/unified-login.php');
    }

    // --------------- GRIDS ---------------
    else{
      $page = isset($_GET['page']) ? $_GET['page'] : 'users';
      $page_titles = array(
        'users' => 'OpenVPN Users',
        'logs' => 'OpenVPN Logs',
        'admins' => 'Web Admins',
        'configs' => 'Configs',
        'filename' => 'File Name',
      );
      $topbar_title = isset($page_titles[$page]) ? $page_titles[$page] : 'OpenVPN Users';
  ?>
    <div class="admin-wrapper">
      <aside class="sidebar">
        <div class="sidebar-header">
          <a href="index.php?admin" style="color:inherit;text-decoration:none"><span class="glyphicon glyphicon-lock"></span> OpenVPN Admin</a>
        </div>
        <ul class="sidebar-nav" id="admin-sidebar-nav">
          <li class="<?= $page=='users'?'active':'' ?>"><a href="index.php?admin&page=users"><span class="glyphicon glyphicon-user"></span> OpenVPN Users</a></li>
          <li class="<?= $page=='logs'?'active':'' ?>"><a href="index.php?admin&page=logs"><span class="glyphicon glyphicon-book"></span> OpenVPN Logs</a></li>
          <li class="<?= $page=='admins'?'active':'' ?>"><a href="index.php?admin&page=admins"><span class="glyphicon glyphicon-king"></span> Web Admins</a></li>
          <li class="<?= $page=='configs'?'active':'' ?>"><a href="index.php?admin&page=configs"><span class="glyphicon glyphicon-edit"></span> Configs</a></li>
          <li class="<?= $page=='filename'?'active':'' ?>"><a href="index.php?admin&page=filename"><span class="glyphicon glyphicon-file"></span> File Name</a></li>
        </ul>
        <div class="sidebar-footer">
          Signed in as <strong><?php echo htmlspecialchars($_SESSION['admin_id']); ?></strong>
        </div>
      </aside>
      <div class="main-content">
        <div class="topbar">
          <span class="topbar-title"><?= htmlspecialchars($topbar_title) ?></span>
          <div>
            <a href="index.php?admin_configuration_get"><button class="btn btn-sm btn-default">Get Config File</button></a>
            <a href="index.php"><button class="btn btn-sm btn-default">Configurations</button></a>
            <a href="index.php?logout"><button class="btn btn-sm btn-danger">Logout <span class="glyphicon glyphicon-off"></span></button></a>
          </div>
        </div>
  <?php
      require(dirname(__FILE__) . '/include/html/grids.php');
  ?>
      </div>
    </div>
  <?php
    }
  ?>  
     <div id="message-stage">
        <!-- used to display application messages (failures / status-notes) to the user -->
     </div>
  </body>
</html>
