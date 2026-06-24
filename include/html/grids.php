<?php
function getHistory($cfg_file, $accordion_id, $open_first = false) {
  ob_start(); ?>
  <h6 class="mt-4 mb-2 text-muted">History</h6>
  <div class="accordion accordion-flush" id="accordion<?= $accordion_id ?>">
    <?php foreach (array_reverse(glob('client-conf/'.basename(pathinfo($cfg_file, PATHINFO_DIRNAME)).'/history/*') ?: []) as $i => $file): ?>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button <?= $i===0&&$open_first?'':'collapsed' ?> py-2" type="button"
                  data-bs-toggle="collapse" data-bs-target="#collapse<?= $accordion_id ?>-<?= $i ?>">
            <?php
              $chunks = explode('_', basename($file), 2);
              printf('[%s] %s', date('Y-m-d H:i', intval($chunks[0])), $chunks[1] ?? '');
            ?>
          </button>
        </h2>
        <div id="collapse<?= $accordion_id ?>-<?= $i ?>" class="accordion-collapse collapse <?= $i===0&&$open_first?'show':'' ?>"
             data-bs-parent="#accordion<?= $accordion_id ?>">
          <div class="accordion-body p-2"><pre class="mb-0 small"><?= htmlspecialchars(file_get_contents($file)) ?></pre></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
  $history = ob_get_contents();
  ob_end_clean();
  return $history;
}
?>

<!-- Stats Row -->
<div class="stats-row" id="stats-row">
  <div class="stat-card">
    <div class="stat-value" id="stat-total-users">–</div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card stat-online">
    <div class="stat-value" id="stat-online-now">–</div>
    <div class="stat-label">Online Now</div>
  </div>
  <div class="stat-card stat-disabled">
    <div class="stat-value" id="stat-disabled">–</div>
    <div class="stat-label">Disabled</div>
  </div>
  <div class="stat-card stat-logs">
    <div class="stat-value" id="stat-log-entries">–</div>
    <div class="stat-label">Log Entries</div>
  </div>
</div>

<div class="tab-content p-3">

  <!-- ═══════════════ DASHBOARD ═══════════════ -->
  <div id="page-dashboard" class="tab-pane-page <?= $page==='dashboard'?'active':'' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0 fw-semibold"><i class="bi bi-activity me-2 text-success"></i>Live Connections</h6>
      <button id="btn-refresh-dashboard" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>
    <div id="dashboard-no-status" class="alert alert-warning d-none small">
      <i class="bi bi-exclamation-triangle me-2"></i>
      OpenVPN status file not found. Ensure OpenVPN is running and the status log path is accessible to the web server.
    </div>
    <table id="table-dashboard" class="table table-sm table-hover">
      <thead class="table-dark">
        <tr>
          <th>User</th>
          <th>Real Address</th>
          <th>Received</th>
          <th>Sent</th>
          <th>Connected Since</th>
        </tr>
      </thead>
      <tbody id="dashboard-tbody">
        <tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>
      </tbody>
    </table>
  </div>

  <!-- ═══════════════ USERS ═══════════════ -->
  <div id="page-users" class="tab-pane-page <?= $page==='users'?'active':'' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-2"></i>OpenVPN Users</h6>
      <?php if($adminRole === 'super-admin'): ?>
      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modal-user-add">
        <i class="bi bi-person-plus me-1"></i>Add User
      </button>
      <?php endif; ?>
    </div>
    <table id="table-users" class="table table-sm table-hover"></table>
  </div>

  <!-- ═══════════════ LOGS ═══════════════ -->
  <div id="page-logs" class="tab-pane-page <?= $page==='logs'?'active':'' ?>">
    <h6 class="mb-3 fw-semibold"><i class="bi bi-journal-text me-2"></i>OpenVPN Logs</h6>
    <table id="table-logs" class="table table-sm table-hover" data-filter-control="true"></table>
  </div>

  <!-- ═══════════════ ADMINS ═══════════════ -->
  <div id="page-admins" class="tab-pane-page <?= $page==='admins'?'active':'' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0 fw-semibold"><i class="bi bi-person-gear me-2"></i>Web Admins</h6>
      <?php if($adminRole === 'super-admin'): ?>
      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modal-admin-add">
        <i class="bi bi-person-plus me-1"></i>Add Admin
      </button>
      <?php endif; ?>
    </div>
    <table id="table-admins" class="table table-sm table-hover"></table>
  </div>

  <!-- ═══════════════ CONFIGS ═══════════════ -->
  <div id="page-configs" class="tab-pane-page <?= $page==='configs'?'active':'' ?>">
    <h6 class="mb-3 fw-semibold"><i class="bi bi-file-earmark-code me-2"></i>Client Configurations</h6>
    <ul class="nav nav-tabs mb-3" id="config-tabs">
      <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#cfg-linux">
          <i class="bi bi-terminal me-1"></i>GNU/Linux
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#cfg-windows">
          <i class="bi bi-windows me-1"></i>Windows
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#cfg-osx">
          <i class="bi bi-apple me-1"></i>macOS / Viscosity
        </a>
      </li>
      <?php if($adminRole === 'super-admin'): ?>
      <li class="nav-item ms-auto">
        <button id="save-config-btn" class="btn btn-sm btn-secondary d-none" id="save-config-btn">
          <i class="bi bi-cloud-upload me-1"></i>Save
        </button>
      </li>
      <?php endif; ?>
    </ul>
    <div class="tab-content" id="config-tab-content">
      <div class="tab-pane fade show active" id="cfg-linux">
        <textarea class="form-control font-monospace cfg-textarea" data-config-file="<?= $cfg_file='client-conf/gnu-linux/client.conf' ?>" rows="18" <?= $adminRole!=='super-admin'?'readonly':'' ?>><?= htmlspecialchars(file_get_contents($cfg_file)) ?></textarea>
        <?= getHistory($cfg_file, 1) ?>
      </div>
      <div class="tab-pane fade" id="cfg-windows">
        <textarea class="form-control font-monospace cfg-textarea" data-config-file="<?= $cfg_file='client-conf/windows/client.ovpn' ?>" rows="18" <?= $adminRole!=='super-admin'?'readonly':'' ?>><?= htmlspecialchars(file_get_contents($cfg_file)) ?></textarea>
        <?= getHistory($cfg_file, 2) ?>
      </div>
      <div class="tab-pane fade" id="cfg-osx">
        <textarea class="form-control font-monospace cfg-textarea" data-config-file="<?= $cfg_file='client-conf/osx-viscosity/client.conf' ?>" rows="18" <?= $adminRole!=='super-admin'?'readonly':'' ?>><?= htmlspecialchars(file_get_contents($cfg_file)) ?></textarea>
        <?= getHistory($cfg_file, 3) ?>
      </div>
    </div>
  </div>

  <!-- ═══════════════ FILENAME ═══════════════ -->
  <div id="page-filename" class="tab-pane-page <?= $page==='filename'?'active':'' ?>">
    <h6 class="mb-3 fw-semibold"><i class="bi bi-file-earmark me-2"></i>Download File Name</h6>
    <p class="text-muted small">The filename users will receive when downloading their .ovpn config (without the .ovpn extension).</p>
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#cfg-filename">
          <i class="bi bi-file-text me-1"></i>Filename
        </a>
      </li>
      <?php if($adminRole === 'super-admin'): ?>
      <li class="nav-item ms-auto">
        <button id="save-config-btn-fn" class="btn btn-sm btn-secondary d-none">
          <i class="bi bi-cloud-upload me-1"></i>Save
        </button>
      </li>
      <?php endif; ?>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="cfg-filename">
        <textarea class="form-control font-monospace cfg-textarea" data-config-file="<?= $cfg_file='client-conf/windows/filename' ?>" rows="3" <?= $adminRole!=='super-admin'?'readonly':'' ?>><?= htmlspecialchars(file_get_contents($cfg_file)) ?></textarea>
        <?= getHistory($cfg_file, 4) ?>
      </div>
    </div>
  </div>

  <!-- ═══════════════ CERTIFICATES ═══════════════ -->
  <div id="page-certificates" class="tab-pane-page <?= $page==='certificates'?'active':'' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0 fw-semibold"><i class="bi bi-patch-check me-2"></i>User Certificates</h6>
      <?php if($adminRole === 'super-admin'): ?>
      <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modal-cert-add">
        <i class="bi bi-plus-circle me-1"></i>Generate Cert
      </button>
      <?php endif; ?>
    </div>
    <div id="cert-no-easyrsa" class="alert alert-info d-none small">
      <i class="bi bi-info-circle me-2"></i>
      EasyRSA not found at <code>/etc/openvpn/easy-rsa</code>. Certificate management requires EasyRSA to be installed by the install script.
    </div>
    <div id="cert-sudo-note" class="alert alert-warning d-none small">
      <i class="bi bi-exclamation-triangle me-2"></i>
      Certificate generation requires <code>www-data</code> to have passwordless sudo access to <code>easyrsa</code>.
      Add to <code>/etc/sudoers.d/openvpn-admin</code>:<br>
      <code>www-data ALL=(ALL) NOPASSWD: /etc/openvpn/easy-rsa/easyrsa</code>
    </div>
    <table class="table table-sm table-hover" id="cert-table">
      <thead class="table-dark">
        <tr>
          <th>Common Name</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="cert-tbody">
        <tr><td colspan="3" class="text-center text-muted py-4">Loading…</td></tr>
      </tbody>
    </table>
  </div>

  <!-- ═══════════════ SETTINGS (SMTP + Notifications) ═══════════════ -->
  <div id="page-settings" class="tab-pane-page <?= $page==='settings'?'active':'' ?>">
    <h6 class="mb-3 fw-semibold"><i class="bi bi-sliders me-2"></i>Settings</h6>
    <?php if($adminRole !== 'super-admin'): ?>
    <div class="alert alert-warning small"><i class="bi bi-lock me-2"></i>Settings are read-only for your role.</div>
    <?php endif; ?>
    <ul class="nav nav-tabs mb-3" id="settings-tabs">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-smtp"><i class="bi bi-envelope me-1"></i>SMTP</a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-notifications"><i class="bi bi-bell me-1"></i>Notifications</a></li>
    </ul>
    <div class="tab-content" id="settings-tab-content">

      <!-- SMTP Tab -->
      <div class="tab-pane fade show active" id="tab-smtp">
        <form id="smtp-form" class="row g-3" style="max-width:640px">
          <div class="col-md-8">
            <label class="form-label">SMTP Host</label>
            <input type="text" class="form-control" name="smtp_host" id="smtp_host" placeholder="smtp.gmail.com" <?= $adminRole!=='super-admin'?'readonly':'' ?>/>
          </div>
          <div class="col-md-4">
            <label class="form-label">Port</label>
            <input type="number" class="form-control" name="smtp_port" id="smtp_port" value="587" <?= $adminRole!=='super-admin'?'readonly':'' ?>/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="smtp_user" id="smtp_user" autocomplete="off" <?= $adminRole!=='super-admin'?'readonly':'' ?>/>
          </div>
          <div class="col-md-6">
            <label class="form-label">Password <span class="text-muted small">(leave blank to keep)</span></label>
            <input type="password" class="form-control" name="smtp_pass" id="smtp_pass" autocomplete="new-password" <?= $adminRole!=='super-admin'?'readonly':'' ?>/>
          </div>
          <div class="col-md-8">
            <label class="form-label">From Address</label>
            <input type="email" class="form-control" name="smtp_from" id="smtp_from" placeholder="noreply@example.com" <?= $adminRole!=='super-admin'?'readonly':'' ?>/>
          </div>
          <div class="col-md-4">
            <label class="form-label">Security</label>
            <select class="form-select" name="smtp_secure" id="smtp_secure" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
              <option value="tls">STARTTLS</option>
              <option value="ssl">SSL/TLS</option>
              <option value="none">None</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">From Name</label>
            <input type="text" class="form-control" name="smtp_from_name" id="smtp_from_name" placeholder="OpenVPN Admin" <?= $adminRole!=='super-admin'?'readonly':'' ?>/>
          </div>
          <?php if($adminRole === 'super-admin'): ?>
          <div class="col-12 d-flex gap-2 flex-wrap align-items-center">
            <button type="submit" class="btn btn-primary" id="smtp-save-btn">
              <i class="bi bi-floppy me-1"></i>Save SMTP Settings
            </button>
            <div class="input-group" style="max-width:300px">
              <input type="email" class="form-control form-control-sm" id="smtp-test-email" placeholder="test@example.com"/>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="smtp-test-btn">
                <i class="bi bi-send me-1"></i>Send Test
              </button>
            </div>
          </div>
          <?php endif; ?>
          <div class="col-12" id="smtp-status"></div>
        </form>
      </div>

      <!-- Notifications Tab -->
      <div class="tab-pane fade" id="tab-notifications">
        <p class="text-muted small mb-3">
          Email notifications are sent to the user's registered email address.
          Connect/disconnect notifications require the OpenVPN scripts to call
          <code>php /var/www/openvpn-admin/include/notify.php</code>.
        </p>
        <form id="notif-form" style="max-width:400px">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="notify_connect" name="notify_connect" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
            <label class="form-check-label" for="notify_connect">
              <strong>On Connect</strong><br>
              <span class="text-muted small">Email user when they connect to VPN</span>
            </label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="notify_disconnect" name="notify_disconnect" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
            <label class="form-check-label" for="notify_disconnect">
              <strong>On Disconnect</strong><br>
              <span class="text-muted small">Email user when they disconnect from VPN</span>
            </label>
          </div>
          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" id="notify_expiry" name="notify_expiry" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
            <label class="form-check-label" for="notify_expiry">
              <strong>Account Expiry</strong><br>
              <span class="text-muted small">Email user 7 days before their account expires</span>
            </label>
          </div>

          <hr/>
          <p class="small fw-semibold mb-1"><i class="bi bi-bell me-1"></i>Admin Notifications (in-app bell)</p>
          <p class="text-muted small mb-3">Control which user events create notifications for super-admins.</p>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="notify_admin_user_add" name="notify_admin_user_add" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
            <label class="form-check-label" for="notify_admin_user_add">
              <strong>User Added</strong><br>
              <span class="text-muted small">Notify super-admins when a new user is created</span>
            </label>
          </div>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" id="notify_admin_user_edit" name="notify_admin_user_edit" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
            <label class="form-check-label" for="notify_admin_user_edit">
              <strong>User Edited</strong><br>
              <span class="text-muted small">Notify super-admins when a user's details are changed</span>
            </label>
          </div>
          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" id="notify_admin_user_delete" name="notify_admin_user_delete" <?= $adminRole!=='super-admin'?'disabled':'' ?>>
            <label class="form-check-label" for="notify_admin_user_delete">
              <strong>User Deleted</strong><br>
              <span class="text-muted small">Notify super-admins when a user is removed</span>
            </label>
          </div>

          <?php if($adminRole === 'super-admin'): ?>
          <button type="submit" class="btn btn-primary" id="notif-save-btn">
            <i class="bi bi-floppy me-1"></i>Save Notification Settings
          </button>
          <?php endif; ?>
          <div id="notif-status" class="mt-2"></div>
        </form>
      </div>
    </div>
  </div>

  <!-- ═══════════════ FAIL2BAN ═══════════════ -->
  <div id="page-fail2ban" class="tab-pane-page <?= $page==='fail2ban'?'active':'' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-x me-2 text-danger"></i>Fail2Ban</h6>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="btn-refresh-fail2ban">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <?php if($adminRole === 'super-admin'): ?>
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modal-ban-ip">
          <i class="bi bi-shield-x me-1"></i>Ban IP
        </button>
        <?php endif; ?>
      </div>
    </div>

    <div id="fail2ban-sudo-note" class="alert alert-info d-none small">
      <i class="bi bi-info-circle me-2"></i>
      Grant the web server sudo access to fail2ban-client:
      <pre class="mb-0 mt-1 bg-dark text-light p-2 rounded small">echo "www-data ALL=(ALL) NOPASSWD: /usr/bin/fail2ban-client" | sudo tee /etc/sudoers.d/openvpn-admin-fail2ban
sudo chmod 440 /etc/sudoers.d/openvpn-admin-fail2ban</pre>
    </div>

    <!-- Summary stats -->
    <div class="row g-3 mb-3" id="fail2ban-summary" style="display:none!important">
      <div class="col-auto">
        <div class="stat-card">
          <div class="stat-value text-danger" id="f2b-total-banned">–</div>
          <div class="stat-label">Currently Banned</div>
        </div>
      </div>
      <div class="col-auto">
        <div class="stat-card">
          <div class="stat-value" id="f2b-jail-count">–</div>
          <div class="stat-label">Active Jails</div>
        </div>
      </div>
      <div class="col-auto">
        <div class="stat-card stat-disabled">
          <div class="stat-value" id="f2b-total-failed">–</div>
          <div class="stat-label">Currently Failing</div>
        </div>
      </div>
    </div>

    <div id="fail2ban-status">
      <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
    </div>
  </div>

</div><!-- /.tab-content -->


<!-- ═══════════ MODALS ═══════════ -->

<!-- Add User Modal -->
<div id="modal-user-add" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" id="modal-user-add-username" class="form-control" autofocus/>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" id="modal-user-add-password" class="form-control"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="mail" id="modal-user-add-mail" class="form-control" placeholder="Optional"/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="modal-user-add-save">
          <i class="bi bi-person-plus me-1"></i>Add User
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Edit User Modal -->
<div id="modal-user-edit" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-user-pk"/>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" id="edit-user-mail" class="form-control"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" id="edit-user-phone" class="form-control"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Start Date</label>
          <input type="date" id="edit-user-start" class="form-control"/>
        </div>
        <div class="mb-3">
          <label class="form-label">End Date</label>
          <input type="date" id="edit-user-end" class="form-control"/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="modal-user-edit-save">
          <i class="bi bi-floppy me-1"></i>Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="modal-reset-pass" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-key me-2"></i>Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">New Password</label>
          <input type="password" id="modal-reset-pass-input" class="form-control" autofocus/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="modal-reset-pass-save">
          <i class="bi bi-key me-1"></i>Reset
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Add Admin Modal -->
<div id="modal-admin-add" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i>Add Admin</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" id="modal-admin-add-username" class="form-control" autofocus/>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" id="modal-admin-add-password" class="form-control"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" id="modal-admin-add-mail" class="form-control" placeholder="Optional"/>
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select id="modal-admin-add-role" class="form-select">
            <option value="super-admin">Super Admin</option>
            <option value="read-only">Read Only</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="modal-admin-add-save">
          <i class="bi bi-person-plus me-1"></i>Add Admin
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Confirm Delete Modal -->
<div id="modal-confirm-delete" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-trash text-danger me-2"></i>Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Delete <strong id="modal-confirm-delete-name"></strong>? This cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="modal-confirm-delete-ok">
          <i class="bi bi-trash me-1"></i>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Generate Certificate Modal -->
<div id="modal-cert-add" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-patch-plus me-2"></i>Generate Certificate</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Common Name (username)</label>
          <input type="text" id="modal-cert-cn" class="form-control" placeholder="e.g. john.doe" autofocus
                 pattern="[a-zA-Z0-9._-]+" title="Letters, numbers, dots, hyphens, underscores only"/>
          <div class="form-text">Must match the VPN username. Alphanumeric, dots, hyphens, underscores only.</div>
        </div>
        <div id="cert-gen-output" class="d-none">
          <label class="form-label text-muted small">Output</label>
          <pre id="cert-gen-pre" class="bg-dark text-light p-2 rounded small" style="max-height:200px;overflow:auto"></pre>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="modal-cert-gen-btn">
          <i class="bi bi-cpu me-1"></i>Generate
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Ban IP Modal (Fail2Ban) -->
<div id="modal-ban-ip" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-shield-x me-2 text-danger"></i>Ban IP Address</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">IP Address</label>
          <input type="text" id="ban-ip-address" class="form-control font-monospace" placeholder="e.g. 1.2.3.4 or 2001:db8::1" autofocus/>
        </div>
        <div class="mb-3">
          <label class="form-label">Jail</label>
          <select id="ban-ip-jail" class="form-select">
            <option value="">— select jail —</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="fail2ban-ban-save">
          <i class="bi bi-shield-x me-1"></i>Ban
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Profile Edit Modal -->
<div id="modal-profile-edit" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-circle me-2"></i>Edit Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Email Address</label>
          <input type="email" id="profile-mail" class="form-control" placeholder="your@email.com"/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="profile-save">
          <i class="bi bi-floppy me-1"></i>Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Send Config Email Modal -->
<div id="modal-send-config" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-envelope me-2"></i>Email Config</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="send-config-cn"/>
        <div class="mb-2">
          <label class="form-label">Recipient Email</label>
          <input type="email" id="send-config-email" class="form-control" placeholder="user@example.com"/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="send-config-btn">
          <i class="bi bi-send me-1"></i>Send
        </button>
      </div>
    </div>
  </div>
</div>
