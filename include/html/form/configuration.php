<div class="row justify-content-center">
  <div class="col-md-5 col-sm-8 col-11">
    <div class="card shadow">
      <div class="card-header py-3">
        <h5 class="mb-0"><i class="bi bi-download me-2"></i>Download VPN Configuration</h5>
      </div>
      <div class="card-body p-4">
        <p class="text-muted small mb-4">Enter your credentials to download your OpenVPN configuration file.</p>
        <form id="configuration_form" method="POST">
          <div class="mb-3">
            <label for="configuration_username" class="form-label">Username</label>
            <input type="text" id="configuration_username" name="configuration_username" class="form-control" autofocus autocomplete="username"/>
          </div>
          <div class="mb-4">
            <label for="configuration_pass" class="form-label">Password</label>
            <input type="password" id="configuration_pass" name="configuration_pass" class="form-control" autocomplete="current-password"/>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button id="configuration_get" name="configuration_get" type="submit" class="btn btn-primary">
              <i class="bi bi-file-earmark-arrow-down me-1"></i>Download .ovpn
            </button>
            <button id="windows_instruction_get" name="windows_instruction_get" type="submit" class="btn btn-outline-secondary">
              <i class="bi bi-windows me-1"></i>Windows Guide
            </button>
            <button id="mac_instruction_get" name="mac_instruction_get" type="submit" class="btn btn-outline-secondary">
              <i class="bi bi-apple me-1"></i>macOS Guide
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
