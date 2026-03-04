<?php
  session_start();

  if(!isset($_SESSION['admin_id']))
    exit -1;

  require(dirname(__FILE__) . '/connect.php');
  require(dirname(__FILE__) . '/functions.php');


  // ---------------- SELECT ----------------
  if(isset($_GET['select'])){

    // Select the users
    if($_GET['select'] == "user"){
      $req = $bdd->prepare('SELECT * FROM user');
      $req->execute();

      if($data = $req->fetch()) {
        do {
          $list[] = array("user_id" => $data['user_id'],
                          "user_pass" => $data['user_pass'],
                          "user_mail" => $data['user_mail'],
                          "user_phone" => $data['user_phone'],
                          "user_online" => $data['user_online'],
                          "user_enable" => $data['user_enable'],
                          "user_start_date" => $data['user_start_date'],
                          "user_end_date" => $data['user_end_date']);
        } while($data = $req->fetch());

        echo json_encode($list);
      }
      // If it is an empty answer, we need to encore an empty json object
      else{
        $list = array();
        echo json_encode($list);
      }
    }

    // Select the logs (aggregated per user)
    else if($_GET['select'] == "log" && isset($_GET['offset'], $_GET['limit'])){
      $offset = intval($_GET['offset']);
      $limit = intval($_GET['limit']);

      $page = "LIMIT $offset, $limit";

      // Count total users with logs
      $count_req = $bdd->query("SELECT COUNT(DISTINCT user_id) FROM log");
      $nb = $count_req->fetchColumn();

      // Aggregate logs per user: total received, total sent, session count, last connection
      $req_string = "SELECT user_id,
        COUNT(*) AS sessions,
        SUM(log_received) AS total_received,
        SUM(log_send) AS total_sent,
        MAX(log_start_time) AS last_connected
        FROM log GROUP BY user_id ORDER BY last_connected DESC $page";
      $req = $bdd->prepare($req_string);
      $req->execute();

      $list = array();

      while($data = $req->fetch()) {
        $received = $data['total_received'];
        $sent = $data['total_sent'];

        if ($received > 1000000000) {
          $received = round($received/1000000000, 1) . " GB";
        } else if ($received > 1000000) {
          $received = floor($received/1000000) . " MB";
        } else {
          $received = floor($received/1000) . " KB";
        }

        if ($sent > 1000000000) {
          $sent = round($sent/1000000000, 1) . " GB";
        } else if ($sent > 1000000) {
          $sent = floor($sent/1000000) . " MB";
        } else {
          $sent = floor($sent/1000) . " KB";
        }

        array_push($list, array(
                                "user_id" => $data['user_id'],
                                "sessions" => $data['sessions'],
                                "total_received" => $received,
                                "total_sent" => $sent,
                                "last_connected" => $data['last_connected']));
      }

      $result = array('total' => intval($nb), 'rows' => $list);
      echo json_encode($result);
    }

    // Select the admins
    else if($_GET['select'] == "admin"){
      $req = $bdd->prepare('SELECT * FROM admin');
      $req->execute();

      if($data = $req->fetch()) {
        do{
          $list[] = array(
                          "admin_id" => $data['admin_id'],
                          "admin_pass" => $data['admin_pass'],
                          "admin_mail" => $data['admin_mail'],
                          "admin_phone" => $data['admin_phone'],
                          "admin_enable" => $data['admin_enable']
                          );
        } while($data = $req->fetch());

        echo json_encode($list);
      }
      else{
        $list = array();
        echo json_encode($list);
      }
    }

    // Select dashboard stats
    else if($_GET['select'] == "stats"){
      $total    = $bdd->query('SELECT COUNT(*) FROM user')->fetchColumn();
      $online   = $bdd->query('SELECT COUNT(*) FROM user WHERE user_online = 1')->fetchColumn();
      $disabled = $bdd->query('SELECT COUNT(*) FROM user WHERE user_enable = 0')->fetchColumn();
      $logs     = $bdd->query('SELECT COUNT(*) FROM log')->fetchColumn();
      echo json_encode([
        'total_users'  => (int)$total,
        'online_now'   => (int)$online,
        'disabled'     => (int)$disabled,
        'log_entries'  => (int)$logs
      ]);
    }
  }

  // ---------------- ADD USER ----------------
  else if(isset($_POST['add_user'], $_POST['user_id'], $_POST['user_pass'])){
    // Put some default values
    $id = $_POST['user_id'];
    $pass = hashPass($_POST['user_pass']);
    $mail = "";
    $phone = "";
    $online = 0;
    $enable = 1;
    $start = date("Y-m-d");
    $end = null;

    $req = $bdd->prepare('INSERT INTO user (user_id, user_pass, user_mail, user_phone, user_online, user_enable, user_start_date, user_end_date)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $req->execute(array($id, $pass, $mail, $phone, $online, $enable, $start, $end));

    $res = array("user_id" => $id,
      "user_pass" => $pass,
      "user_mail" => $mail ,
      "user_phone" => $phone,
      "user_online" => $online,
      "user_enable" => $enable,
      "user_start_date" => $start,
      "user_end_date" => $end
    );

    echo json_encode($res);
  }

  // ---------------- UPDATE USER ----------------
  else if(isset($_POST['set_user'])){
    $valid = array("user_id", "user_pass", "user_mail", "user_phone", "user_enable", "user_start_date", "user_end_date");

    $field = $_POST['name'];
    $value = $_POST['value'];
    $pk = $_POST['pk'];

    if (!isset($field) || !isset($pk) || !in_array($field, $valid)) {
      return;
    }

    if ($field === 'user_pass') {
      $value = hashPass($value);
    }
    else if (($field === 'user_start_date' || $field === 'user_end_date') && $value === '') {
      $value = null;
    }

    // /!\ SQL injection: field was checked with in_array function
    $req_string = 'UPDATE user SET ' . $field . ' = ? WHERE user_id = ?';
    $req = $bdd->prepare($req_string);
    $req->execute(array($value, $pk));
  }

  // ---------------- REMOVE USER ----------------
  else if(isset($_POST['del_user'], $_POST['del_user_id'])){
    $req = $bdd->prepare('DELETE FROM user WHERE user_id = ?');
    $req->execute(array($_POST['del_user_id']));
  }

  // ---------------- ADD ADMIN ----------------
  else if(isset($_POST['add_admin'], $_POST['admin_id'], $_POST['admin_pass'])){
    $id = $_POST['admin_id'];
    $pass = hashPass($_POST['admin_pass']);
    $mail = "";
    $phone = "";
    $enable = 1;

    $req = $bdd->prepare('INSERT INTO admin (admin_id, admin_pass, admin_mail, admin_phone, admin_enable) VALUES (?, ?, ?, ?, ?)');
    $req->execute(array($id, $pass, $mail, $phone, $enable));

    $res = array("admin_id" => $id,
      "admin_pass" => $pass,
      "admin_mail" => $mail,
      "admin_phone" => $phone,
      "admin_enable" => $enable
    );

    echo json_encode($res);
  }

  // ---------------- UPDATE ADMIN ----------------
  else if(isset($_POST['set_admin'])){
    $valid = array("admin_id", "admin_pass", "admin_mail", "admin_phone", "admin_enable");

    $field = $_POST['name'];
    $value = $_POST['value'];
    $pk = $_POST['pk'];

    if (!isset($field) || !isset($pk) || !in_array($field, $valid)) {
      return;
    }

    if ($field === 'admin_pass') {
      $value = hashPass($value);
    }

    $req_string = 'UPDATE admin SET ' . $field . ' = ? WHERE admin_id = ?';
    $req = $bdd->prepare($req_string);
    $req->execute(array($value, $pk));
  }

  // ---------------- REMOVE ADMIN ----------------
  else if(isset($_POST['del_admin'], $_POST['del_admin_id'])){
    $req = $bdd->prepare('DELETE FROM admin WHERE admin_id = ?');
    $req->execute(array($_POST['del_admin_id']));
  }

  // ---------------- UPDATE CONFIG ----------------
  else if(isset($_POST['update_config'])){

      $pathinfo = pathinfo($_POST['config_file']);

      $config_full_uri = $_POST['config_file']; // the complete path to the file, including the file (name) its self and the fully qualified path
      $config_full_path = $pathinfo['dirname']; // path to file (without filename its self)
      $config_name = basename($_POST['config_file']); // config file name only (without path)
      $config_parent_dir = basename($config_full_path); // name of the dir that contains the config file (without path)

      /*
       * create backup for history
       */
      if (!file_exists($dir="../$config_full_path/history"))
         mkdir($dir, 0777, true);
      $ts = time();
      copy("../$config_full_uri", "../$config_full_path/history/${ts}_${config_name}");

      /*
       *  write config
       */
      $conf_success = file_put_contents('../'.$_POST['config_file'], $_POST['config_content']);

      echo json_encode([
        'debug' => [
            'config_file' => $_POST['config_file'],
            'config_content' => $_POST['config_content']
        ],
        'config_success' => $conf_success !== false,
      ]);
  }

?>
