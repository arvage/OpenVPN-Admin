<?php
session_start();
require dirname(__FILE__) . '/../include/connect.php';
require dirname(__FILE__) . '/../include/functions.php';

header('Content-Type: text/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$valid_pages = ['dashboard','users','logs','admins','configs','filename','certificates','settings','fail2ban'];
$page        = (isset($_GET['page']) && in_array($_GET['page'], $valid_pages, true))
                ? $_GET['page'] : 'dashboard';
$adminRole   = !empty($_SESSION['admin_id']) ? getCurrentAdminRole($bdd) : null;
?>
window.ADMIN_ROLE   = <?= json_encode($adminRole) ?>;
window.CURRENT_PAGE = <?= json_encode($page) ?>;
window.CSRF_TOKEN   = <?= json_encode(generateCsrfToken()) ?>;
