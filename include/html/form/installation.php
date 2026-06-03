<div class="row justify-content-center">
  <div class="col-md-5 col-sm-8 col-11">
    <div class="card shadow">
      <div class="card-header py-3">
        <h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i>First-Time Installation</h5>
      </div>
      <div class="card-body p-4">
        <p class="text-muted small mb-4">Create the initial administrator account to complete setup.</p>
        <form id="install_form" method="POST">
          <div class="mb-3">
            <label for="admin_username" class="form-label">Admin Username</label>
            <input type="text" id="admin_username" name="admin_username" class="form-control" autofocus autocomplete="username"/>
          </div>
          <div class="mb-3">
            <label for="admin_pass" class="form-label">Admin Password</label>
            <input type="password" id="admin_pass" name="admin_pass" class="form-control" autocomplete="new-password"/>
          </div>
          <div class="mb-4">
            <label for="repeat_admin_pass" class="form-label">Confirm Password</label>
            <input type="password" id="repeat_admin_pass" name="repeat_admin_pass" class="form-control" autocomplete="new-password"/>
          </div>
          <div class="d-grid">
            <button id="install" name="install" type="submit" class="btn btn-success">
              <i class="bi bi-check2-circle me-2"></i>Install
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
