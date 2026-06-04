<div class="row justify-content-center">
  <div class="col-md-4 col-sm-8 col-11">
    <div class="card login-card shadow">
      <div class="card-header text-center py-3">
        <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>OpenVPN Admin</h5>
      </div>
      <div class="card-body p-4">
        <form id="login_form" method="POST">
          <div class="mb-3">
            <label for="admin_username" class="form-label">Username</label>
            <input type="text" id="admin_username" name="admin_username" class="form-control" autofocus autocomplete="username"/>
          </div>
          <div class="mb-3">
            <label for="admin_pass" class="form-label">Password</label>
            <input type="password" id="admin_pass" name="admin_pass" class="form-control" autocomplete="current-password"/>
          </div>
          <div class="d-grid mt-4">
            <button id="admin_login" name="admin_login" type="submit" class="btn btn-primary">
              <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
          </div>
        </form>
      </div>
    </div>
    <p class="text-center mt-3 mb-0" style="font-size:0.78em; color:#999;">
      <i class="bi bi-github me-1"></i>
      <a href="https://github.com/arvage/OpenVPN-Admin" target="_blank" rel="noopener" style="color:#999;">
        by Armin &mdash; github.com/arvage/OpenVPN-Admin
      </a>
    </p>
  </div>
</div>
