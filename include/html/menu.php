<nav class="navbar navbar-expand navbar-dark bg-dark mb-4 px-3">
  <a class="navbar-brand" href="index.php">
    <i class="bi bi-shield-lock me-2"></i>OpenVPN Admin
  </a>
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link<?php if(!isset($_GET['admin'])) echo ' active'; ?>" href="index.php">
        <i class="bi bi-download me-1"></i>Get Config
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link<?php if(isset($_GET['admin'])) echo ' active'; ?>" href="index.php?admin">
        <i class="bi bi-speedometer2 me-1"></i>Admin Panel
      </a>
    </li>
  </ul>
</nav>
