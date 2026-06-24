$(function () {
  'use strict';

  var gridsUrl = 'include/grids.php';
  var isSuperAdmin = (window.ADMIN_ROLE === 'super-admin');
  var currentPage = window.CURRENT_PAGE || 'dashboard';

  // ─── TOAST ───
  function toast(msg, type) {
    type = type || 'secondary';
    var icons = { success: 'check-circle-fill', danger: 'x-circle-fill', warning: 'exclamation-triangle-fill', secondary: 'info-circle-fill' };
    var icon  = icons[type] || icons.secondary;
    var id    = 'toast-' + Date.now();
    var html  = '<div id="' + id + '" class="toast align-items-center text-bg-' + type + ' border-0" role="alert">'
              + '<div class="d-flex"><div class="toast-body"><i class="bi bi-' + icon + ' me-2"></i>' + msg + '</div>'
              + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
    $('#toast-container').append(html);
    var el = document.getElementById(id);
    var t  = new bootstrap.Toast(el, { delay: 3500 });
    t.show();
    el.addEventListener('hidden.bs.toast', function() { el.remove(); });
  }

  function onError(xhr) {
    var msg = 'Request failed';
    try { var j = JSON.parse(xhr.responseText); if (j.error) msg = j.error; } catch(e) {}
    toast(msg, 'danger');
  }

  function fmtBytes(n) {
    n = parseInt(n) || 0;
    if (n >= 1073741824) return (n/1073741824).toFixed(2) + ' GB';
    if (n >= 1048576)    return (n/1048576).toFixed(1)    + ' MB';
    if (n >= 1024)       return Math.round(n/1024)        + ' KB';
    return n + ' B';
  }

  // ─── SIDEBAR MOBILE TOGGLE ───
  $('#sidebar-toggle').on('click', function() {
    $('#sidebar').toggleClass('open');
  });

  // ─── STATS ───
  function loadStats() {
    $.getJSON(gridsUrl, { select: 'stats' }, function(d) {
      $('#stat-total-users').text(d.total_users);
      $('#stat-online-now').text(d.online_now);
      $('#stat-disabled').text(d.disabled);
      $('#stat-log-entries').text(d.log_entries);
    });
  }
  loadStats();
  setInterval(function() {
    loadStats();
    if (currentPage === 'dashboard') loadDashboard();
    if (currentPage === 'users')     $userTable.bootstrapTable('refresh');
  }, 10000);


  // ════════════════════ DASHBOARD ════════════════════

  function loadDashboard() {
    $.getJSON(gridsUrl, { select: 'dashboard' }, function(data) {
      var $tbody = $('#dashboard-tbody');
      $tbody.empty();
      if (data.error) {
        $('#dashboard-no-status').removeClass('d-none').text(data.error || 'Status file not found.');
        $tbody.html('<tr><td colspan="5" class="text-center text-muted py-4">No data available.</td></tr>');
        return;
      }
      $('#dashboard-no-status').addClass('d-none');
      if (!data.clients || data.clients.length === 0) {
        $tbody.html('<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-wifi-off me-2"></i>No active connections.</td></tr>');
        return;
      }
      data.clients.forEach(function(c) {
        $tbody.append(
          '<tr>' +
          '<td><i class="bi bi-person-circle me-1 text-success"></i>' + c.common_name + '</td>' +
          '<td><code>' + c.real_address + '</code></td>' +
          '<td>' + c.rx_human + '</td>' +
          '<td>' + c.tx_human + '</td>' +
          '<td><small>' + c.connected_since + '</small></td>' +
          '</tr>'
        );
      });
    }).fail(function() {
      $('#dashboard-no-status').removeClass('d-none');
    });
  }

  if (currentPage === 'dashboard') loadDashboard();

  $('#btn-refresh-dashboard').on('click', function() {
    $('#dashboard-tbody').html('<tr><td colspan="5" class="text-center text-muted py-4">Loading…</td></tr>');
    loadDashboard();
    loadStats();
    toast('Refreshed', 'secondary');
  });


  // ════════════════════ USERS ════════════════════

  var $userTable = $('#table-users');
  var $modalUserAdd = new bootstrap.Modal('#modal-user-add');
  var $modalUserEdit = new bootstrap.Modal('#modal-user-edit');
  var $modalResetPass = new bootstrap.Modal('#modal-reset-pass');

  function onlineFormatter(val) {
    return parseInt(val) === 1
      ? '<span class="led led-green" title="Online"></span>'
      : '<span class="led led-red" title="Offline"></span>';
  }

  function enableFormatter(val) {
    return '<label class="toggle-switch">' +
      '<input type="checkbox" class="enable-toggle" ' + (parseInt(val) === 1 ? 'checked' : '') + '/>' +
      '<span class="toggle-slider"></span></label>';
  }

  function passFormatter(val, row) {
    if (!isSuperAdmin) return '<span class="pass-mask">••••••</span>';
    return '<span class="pass-mask">••••••</span>' +
      '<button class="btn btn-xs btn-warning btn-sm reset-pass-btn" data-user-id="' + row.user_id + '" style="padding:1px 6px;font-size:0.75em">Reset</button>';
  }

  function userActionsFormatter(val, row) {
    var html = '';
    if (isSuperAdmin) {
      html += '<button class="btn btn-xs btn-sm btn-outline-primary me-1 user-edit-btn" data-id="' + row.user_id + '" '
            + 'data-mail="' + (row.user_mail||'') + '" data-phone="' + (row.user_phone||'') + '" '
            + 'data-start="' + (row.user_start_date||'') + '" data-end="' + (row.user_end_date||'') + '" '
            + 'style="padding:1px 6px;font-size:0.75em" title="Edit"><i class="bi bi-pencil"></i></button>';
      html += '<button class="btn btn-xs btn-sm btn-outline-danger user-del-btn" data-id="' + row.user_id + '" '
            + 'style="padding:1px 6px;font-size:0.75em" title="Delete"><i class="bi bi-trash"></i></button>';
    }
    return html || '<span class="text-muted small">–</span>';
  }

  function userRowStyle(row) {
    if (row.user_end_date) {
      var today = new Date().toISOString().split('T')[0];
      if (row.user_end_date < today) return { classes: 'user-expired' };
    }
    return {};
  }

  $userTable.bootstrapTable({
    url: gridsUrl,
    sortable: false,
    rowStyle: userRowStyle,
    queryParams: function(p) { p.select = 'user'; return p; },
    columns: [
      { title: 'Username',   field: 'user_id' },
      { title: 'Password',   field: 'user_pass',       formatter: passFormatter },
      { title: 'Email',      field: 'user_mail' },
      { title: 'Phone',      field: 'user_phone' },
      { title: 'Online',     field: 'user_online',     formatter: onlineFormatter, align: 'center' },
      {
        title: 'Enabled', field: 'user_enable', align: 'center',
        formatter: enableFormatter,
        events: {
          'change input.enable-toggle': function(e, val, row) {
            if (!isSuperAdmin) return;
            $.post(gridsUrl, { set_user: 1, name: 'user_enable', value: e.target.checked ? '1' : '0', pk: row.user_id },
              function() { loadStats(); }, 'json').fail(onError);
          }
        }
      },
      { title: 'Start',      field: 'user_start_date' },
      { title: 'End',        field: 'user_end_date' },
      { title: '',           field: 'actions', formatter: userActionsFormatter, align: 'right' },
    ]
  });

  // Add user
  $('#modal-user-add-save').on('click', function() {
    var id   = $('#modal-user-add-username').val().trim();
    var pass = $('#modal-user-add-password').val();
    var mail = $('#modal-user-add-mail').val().trim();
    if (!id || !pass) { toast('Username and password required.', 'warning'); return; }
    $.post(gridsUrl, { add_user: 1, user_id: id, user_pass: pass, user_mail: mail }, function() {
      $modalUserAdd.hide();
      $userTable.bootstrapTable('refresh');
      loadStats();
      toast('User added.', 'success');
    }, 'json').fail(onError);
  });

  // Edit user
  $(document).on('click', '.user-edit-btn', function() {
    var $btn = $(this);
    $('#edit-user-pk').val($btn.data('id'));
    $('#edit-user-mail').val($btn.data('mail'));
    $('#edit-user-phone').val($btn.data('phone'));
    $('#edit-user-start').val($btn.data('start'));
    $('#edit-user-end').val($btn.data('end'));
    $modalUserEdit.show();
  });

  $('#modal-user-edit-save').on('click', function() {
    var pk    = $('#edit-user-pk').val();
    var mail  = $('#edit-user-mail').val();
    var phone = $('#edit-user-phone').val();
    var start = $('#edit-user-start').val();
    var end   = $('#edit-user-end').val();
    var reqs  = [
      $.post(gridsUrl, { set_user: 1, name: 'user_mail',       value: mail,  pk: pk }),
      $.post(gridsUrl, { set_user: 1, name: 'user_phone',      value: phone, pk: pk }),
      $.post(gridsUrl, { set_user: 1, name: 'user_start_date', value: start, pk: pk }),
      $.post(gridsUrl, { set_user: 1, name: 'user_end_date',   value: end,   pk: pk }),
    ];
    $.when.apply($, reqs).done(function() {
      $modalUserEdit.hide();
      $userTable.bootstrapTable('refresh');
      toast('User updated.', 'success');
    }).fail(onError);
  });

  // Delete user
  var _pendingDeleteType = null, _pendingDeleteId = null;
  var $confirmModal = new bootstrap.Modal('#modal-confirm-delete');

  $(document).on('click', '.user-del-btn', function() {
    _pendingDeleteType = 'user';
    _pendingDeleteId   = $(this).data('id');
    $('#modal-confirm-delete-name').text(_pendingDeleteId);
    $confirmModal.show();
  });

  // Reset password
  $(document).on('click', '.reset-pass-btn', function() {
    var userId  = $(this).data('user-id');
    var adminId = $(this).data('admin-id');
    $('#modal-reset-pass-input').val('');
    if (userId)  $('#modal-reset-pass').data('type', 'user').data('id', userId);
    if (adminId) $('#modal-reset-pass').data('type', 'admin').data('id', adminId);
    $modalResetPass.show();
  });

  $('#modal-reset-pass-save').on('click', function() {
    var type = $('#modal-reset-pass').data('type');
    var id   = $('#modal-reset-pass').data('id');
    var pass = $('#modal-reset-pass-input').val();
    if (!pass) return;
    var postData = type === 'admin'
      ? { set_admin: 1, name: 'admin_pass', value: pass, pk: id }
      : { set_user:  1, name: 'user_pass',  value: pass, pk: id };
    $.post(gridsUrl, postData, function() {
      $modalResetPass.hide();
      toast('Password reset.', 'success');
    }, 'json').fail(onError);
  });


  // ════════════════════ LOGS ════════════════════

  var $logTable = $('#table-logs');

  $logTable.bootstrapTable({
    url: gridsUrl,
    sidePagination: 'server',
    pagination: true,
    queryParams: function(p) { p.select = 'log'; return p; },
    columns: [
      { title: 'User',           field: 'user_id',        filterControl: 'input' },
      { title: 'Sessions',       field: 'sessions',       align: 'center' },
      { title: 'Total Received', field: 'total_received', align: 'center' },
      { title: 'Total Sent',     field: 'total_sent',     align: 'center' },
      { title: 'Last Connected', field: 'last_connected' },
    ]
  });


  // ════════════════════ ADMINS ════════════════════

  var $adminTable = $('#table-admins');
  var $modalAdminAdd = new bootstrap.Modal('#modal-admin-add');

  function adminActionsFormatter(val, row) {
    var html = '';
    if (isSuperAdmin && row.admin_id !== (window._currentAdmin || '')) {
      html += '<button class="btn btn-sm btn-outline-secondary me-1 admin-role-btn" '
            + 'data-id="' + row.admin_id + '" data-role="' + row.admin_role + '" '
            + 'style="padding:1px 6px;font-size:0.75em" title="Toggle Role">'
            + '<i class="bi bi-arrow-repeat"></i></button>';
      html += '<button class="btn btn-sm btn-outline-danger admin-del-btn" data-id="' + row.admin_id + '" '
            + 'style="padding:1px 6px;font-size:0.75em" title="Delete"><i class="bi bi-trash"></i></button>';
    }
    return html || '<span class="text-muted small">–</span>';
  }

  function adminPassFormatter(val, row) {
    if (!isSuperAdmin) return '<span class="pass-mask">••••••</span>';
    return '<span class="pass-mask">••••••</span>' +
      '<button class="btn btn-sm btn-warning reset-pass-btn" data-admin-id="' + row.admin_id + '" style="padding:1px 6px;font-size:0.75em">Reset</button>';
  }

  function roleFormatter(val) {
    return val === 'super-admin'
      ? '<span class="badge bg-primary">Super Admin</span>'
      : '<span class="badge bg-secondary">Read Only</span>';
  }

  $adminTable.bootstrapTable({
    url: gridsUrl,
    queryParams: function(p) { p.select = 'admin'; return p; },
    columns: [
      { title: 'Username', field: 'admin_id' },
      { title: 'Password', field: 'admin_pass',   formatter: adminPassFormatter },
      { title: 'Email',    field: 'admin_mail' },
      { title: 'Role',     field: 'admin_role',   formatter: roleFormatter, align: 'center' },
      { title: '',         field: 'actions',       formatter: adminActionsFormatter, align: 'right' },
    ]
  });

  $('#modal-admin-add-save').on('click', function() {
    var id   = $('#modal-admin-add-username').val().trim();
    var pass = $('#modal-admin-add-password').val();
    var mail = $('#modal-admin-add-mail').val().trim();
    var role = $('#modal-admin-add-role').val();
    if (!id || !pass) { toast('Username and password required.', 'warning'); return; }
    $.post(gridsUrl, { add_admin: 1, admin_id: id, admin_pass: pass, admin_mail: mail, admin_role: role }, function() {
      $modalAdminAdd.hide();
      $adminTable.bootstrapTable('refresh');
      toast('Admin added.', 'success');
    }, 'json').fail(onError);
  });

  $(document).on('click', '.admin-del-btn', function() {
    _pendingDeleteType = 'admin';
    _pendingDeleteId   = $(this).data('id');
    $('#modal-confirm-delete-name').text(_pendingDeleteId);
    $confirmModal.show();
  });

  $(document).on('click', '.admin-role-btn', function() {
    var id      = $(this).data('id');
    var newRole = $(this).data('role') === 'super-admin' ? 'read-only' : 'super-admin';
    $.post(gridsUrl, { set_admin: 1, name: 'admin_role', value: newRole, pk: id }, function() {
      $adminTable.bootstrapTable('refresh');
      toast('Role updated to ' + newRole + '.', 'success');
    }, 'json').fail(onError);
  });

  // Shared confirm delete handler
  $('#modal-confirm-delete-ok').on('click', function() {
    if (_pendingDeleteType === 'user') {
      $.post(gridsUrl, { del_user: 1, del_user_id: _pendingDeleteId }, function() {
        $userTable.bootstrapTable('refresh');
        loadStats();
        toast('User deleted.', 'success');
      }, 'json').fail(onError);
    } else if (_pendingDeleteType === 'admin') {
      $.post(gridsUrl, { del_admin: 1, del_admin_id: _pendingDeleteId }, function() {
        $adminTable.bootstrapTable('refresh');
        toast('Admin deleted.', 'success');
      }, 'json').fail(onError);
    }
    $confirmModal.hide();
    _pendingDeleteType = _pendingDeleteId = null;
  });


  // ════════════════════ CONFIGS ════════════════════

  // Track dirty state per textarea
  $(document).on('input', '.cfg-textarea', function() {
    $(this).data('dirty', true);
    $('#save-config-btn, #save-config-btn-fn').removeClass('d-none btn-secondary').addClass('btn-warning');
  });

  // Debounce save on blur
  $(document).on('blur', '.cfg-textarea', function() {
    if ($(this).data('dirty')) {
      var $ta = $(this);
      $.post(gridsUrl, { update_config: 1, config_file: $ta.data('config-file'), config_content: $ta.val() },
        function(r) {
          if (r.config_success) {
            $ta.data('dirty', false);
            $('#save-config-btn, #save-config-btn-fn').addClass('btn-success').removeClass('btn-warning');
            setTimeout(function() {
              $('#save-config-btn, #save-config-btn-fn').addClass('d-none btn-secondary').removeClass('btn-success btn-warning');
            }, 2500);
            toast('Config saved.', 'success');
          } else {
            toast('Failed to save config.', 'danger');
          }
        }, 'json'
      ).fail(onError);
    }
  });


  // ════════════════════ CERTIFICATES ════════════════════

  function loadCertificates() {
    $.getJSON(gridsUrl, { select: 'certificates' }, function(data) {
      var $tbody = $('#cert-tbody');
      $tbody.empty();
      if (data.error) {
        $('#cert-no-easyrsa').removeClass('d-none').html('<i class="bi bi-info-circle me-2"></i>' + data.error);
        $tbody.html('<tr><td colspan="3" class="text-center text-muted py-4">EasyRSA not configured.</td></tr>');
        return;
      }
      $('#cert-no-easyrsa').addClass('d-none');
      if (!data.length) {
        $tbody.html('<tr><td colspan="3" class="text-center text-muted py-4">No user certificates found.</td></tr>');
        return;
      }
      data.forEach(function(c) {
        var badgeClass = c.status === 'V' ? 'badge-cert-v' : (c.status === 'R' ? 'badge-cert-r' : 'badge-cert-e');
        var actions = '';
        if (c.status === 'V' && c.has_cert) {
          actions += '<a href="include/grids.php?cert_download=1&cert_name=' + encodeURIComponent(c.cn) + '" class="btn btn-xs btn-sm btn-outline-primary me-1" style="padding:1px 6px;font-size:0.75em" title="Download .ovpn"><i class="bi bi-download"></i></a>';
          actions += '<button class="btn btn-xs btn-sm btn-outline-secondary me-1 send-cfg-btn" data-cn="' + c.cn + '" style="padding:1px 6px;font-size:0.75em" title="Email config"><i class="bi bi-envelope"></i></button>';
        }
        if (isSuperAdmin && c.status === 'V') {
          actions += '<button class="btn btn-xs btn-sm btn-outline-danger cert-revoke-btn" data-cn="' + c.cn + '" style="padding:1px 6px;font-size:0.75em" title="Revoke"><i class="bi bi-x-circle"></i></button>';
        }
        $tbody.append(
          '<tr>' +
          '<td>' + c.cn + '</td>' +
          '<td><span class="badge ' + badgeClass + '">' + c.status_label + '</span></td>' +
          '<td class="text-end">' + actions + '</td>' +
          '</tr>'
        );
      });
    }).fail(function() {
      $('#cert-tbody').html('<tr><td colspan="3" class="text-center text-muted py-4">Could not load certificates.</td></tr>');
    });
  }

  if (currentPage === 'certificates') loadCertificates();

  // Generate cert
  $('#modal-cert-gen-btn').on('click', function() {
    var cn = $('#modal-cert-cn').val().trim();
    if (!cn || !/^[a-zA-Z0-9._-]+$/.test(cn)) {
      toast('Invalid common name.', 'warning');
      return;
    }
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Generating…');
    $('#cert-gen-output').removeClass('d-none');
    $('#cert-gen-pre').text('Running easyrsa, this may take a moment…');

    $.post(gridsUrl, { cert_generate: 1, cert_name: cn }, function(r) {
      $('#cert-gen-pre').text(r.output || '(no output)');
      if (r.ok) {
        toast('Certificate generated for ' + cn, 'success');
        loadCertificates();
        setTimeout(function() { bootstrap.Modal.getInstance('#modal-cert-add').hide(); }, 2000);
      } else {
        toast('Generation failed. Check sudo access.', 'danger');
        $('#cert-sudo-note').removeClass('d-none');
      }
    }, 'json').fail(onError).always(function() {
      $btn.prop('disabled', false).html('<i class="bi bi-cpu me-1"></i>Generate');
    });
  });

  // Revoke cert
  $(document).on('click', '.cert-revoke-btn', function() {
    var cn = $(this).data('cn');
    if (!confirm('Revoke certificate for ' + cn + '? This cannot be undone.')) return;
    $.post(gridsUrl, { cert_revoke: 1, cert_name: cn }, function(r) {
      if (r.ok) { toast('Certificate revoked for ' + cn, 'success'); loadCertificates(); }
      else       { toast('Revocation failed.', 'danger'); }
    }, 'json').fail(onError);
  });

  // Send config via email
  var $sendCfgModal = new bootstrap.Modal('#modal-send-config');
  $(document).on('click', '.send-cfg-btn', function() {
    $('#send-config-cn').val($(this).data('cn'));
    $('#send-config-email').val('');
    $sendCfgModal.show();
  });

  $('#send-config-btn').on('click', function() {
    var cn    = $('#send-config-cn').val();
    var email = $('#send-config-email').val().trim();
    if (!email) { toast('Enter an email address.', 'warning'); return; }
    $.post(gridsUrl, { send_config_email: 1, cert_name: cn, email_to: email }, function(r) {
      if (r.ok) { $sendCfgModal.hide(); toast('Config emailed to ' + email, 'success'); }
      else       { toast('Failed to send email: ' + (r.error||''), 'danger'); }
    }, 'json').fail(onError);
  });


  // ════════════════════ SMTP SETTINGS ════════════════════

  function loadSmtpSettings() {
    $.getJSON(gridsUrl, { select: 'smtp' }, function(d) {
      if (!d || d.error) return;
      $('#smtp_host').val(d.smtp_host || '');
      $('#smtp_port').val(d.smtp_port || 587);
      $('#smtp_user').val(d.smtp_user || '');
      $('#smtp_from').val(d.smtp_from || '');
      $('#smtp_from_name').val(d.smtp_from_name || 'OpenVPN Admin');
      $('#smtp_secure').val(d.smtp_secure || 'tls');
      // notifications
      $('#notify_connect').prop('checked',    !!parseInt(d.notify_connect));
      $('#notify_disconnect').prop('checked', !!parseInt(d.notify_disconnect));
      $('#notify_expiry').prop('checked',     !!parseInt(d.notify_expiry));
    });
  }

  if (currentPage === 'settings') loadSmtpSettings();

  $('#smtp-form').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#smtp-save-btn').prop('disabled', true);
    var data = {
      save_smtp:       1,
      smtp_host:       $('#smtp_host').val(),
      smtp_port:       $('#smtp_port').val(),
      smtp_user:       $('#smtp_user').val(),
      smtp_pass:       $('#smtp_pass').val(),
      smtp_from:       $('#smtp_from').val(),
      smtp_from_name:  $('#smtp_from_name').val(),
      smtp_secure:     $('#smtp_secure').val(),
      notify_connect:  $('#notify_connect').is(':checked') ? 1 : 0,
      notify_disconnect: $('#notify_disconnect').is(':checked') ? 1 : 0,
      notify_expiry:   $('#notify_expiry').is(':checked') ? 1 : 0,
    };
    $.post(gridsUrl, data, function() {
      toast('SMTP settings saved.', 'success');
    }, 'json').fail(onError).always(function() { $btn.prop('disabled', false); });
  });

  $('#notif-form').on('submit', function(e) {
    e.preventDefault();
    var $btn = $('#notif-save-btn').prop('disabled', true);
    $.post(gridsUrl, {
      save_smtp:         1,
      notify_connect:    $('#notify_connect').is(':checked') ? 1 : 0,
      notify_disconnect: $('#notify_disconnect').is(':checked') ? 1 : 0,
      notify_expiry:     $('#notify_expiry').is(':checked') ? 1 : 0,
    }, function() {
      toast('Notification settings saved.', 'success');
    }, 'json').fail(onError).always(function() { $btn.prop('disabled', false); });
  });

  $('#smtp-test-btn').on('click', function() {
    var email = $('#smtp-test-email').val().trim();
    if (!email) { toast('Enter a recipient email for the test.', 'warning'); return; }
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.post(gridsUrl, { test_smtp: 1, test_email: email }, function(r) {
      if (r.ok) toast('Test email sent to ' + email, 'success');
      else      toast('Failed: ' + (r.error||'check settings'), 'danger');
    }, 'json').fail(onError).always(function() {
      $btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i>Send Test');
    });
  });


  // ════════════════════ NOTIFICATIONS ════════════════════

  function refreshNotifications() {
    $.getJSON(gridsUrl, { select: 'notifications' }, function(data) {
      if (!data.is_super) return;

      if (data.count > 0) {
        $('#notification-count').text(data.count).show();
      } else {
        $('#notification-count').hide();
      }

      var $list = $('#notification-list');
      $list.find('li.notification-item').remove();

      if (!data.notifications || data.notifications.length === 0) {
        $list.append('<li class="notification-item px-3 py-2 text-muted small">No notifications yet.</li>');
      } else {
        $.each(data.notifications, function(i, n) {
          var icon, iconClass;
          if (n.notification_type === 'add_user') {
            icon = 'person-plus'; iconClass = 'text-success';
          } else if (n.notification_type === 'del_user') {
            icon = 'person-x'; iconClass = 'text-danger';
          } else {
            icon = 'pencil'; iconClass = 'text-primary';
          }
          var cls = n.is_read ? '' : 'notif-unread';
          $list.append(
            '<li class="notification-item px-3 py-2 border-bottom ' + cls + '">' +
            '<div class="d-flex align-items-start gap-2">' +
            '<i class="bi bi-' + icon + ' ' + iconClass + ' mt-1 flex-shrink-0"></i>' +
            '<div><div class="small">' + $('<span>').text(n.notification_detail).html() + '</div>' +
            '<div class="text-muted" style="font-size:0.75em">' + n.notification_created_at + '</div>' +
            '</div></div></li>'
          );
        });
      }
    });
  }

  $('#notification-bell').on('click', function() {
    if ($('#notification-count').is(':visible')) {
      $.post(gridsUrl, { mark_notifications_read: 1 }, function() {
        $('#notification-count').hide();
        $('.notif-unread').removeClass('notif-unread');
      });
    }
  });

  $('#mark-all-read').on('click', function(e) {
    e.preventDefault();
    e.stopPropagation();
    $.post(gridsUrl, { mark_notifications_read: 1 }, function() {
      $('#notification-count').hide();
      $('.notif-unread').removeClass('notif-unread');
      toast('All notifications marked as read.', 'success');
    });
  });

  if ($('#notif-wrapper').length) {
    refreshNotifications();
    setInterval(refreshNotifications, 30000);
  }


  // ════════════════════ PROFILE ════════════════════

  $(document).on('show.bs.modal', '#modal-profile-edit', function() {
    $.getJSON(gridsUrl, { select: 'my_profile' }, function(d) {
      $('#profile-mail').val(d.admin_mail || '');
    });
  });

  $('#profile-save').on('click', function() {
    var mail = $('#profile-mail').val().trim();
    $.post(gridsUrl, { set_admin_profile: 1, admin_mail: mail }, function(r) {
      if (r.ok) {
        bootstrap.Modal.getInstance('#modal-profile-edit').hide();
        toast('Profile updated.', 'success');
      } else {
        toast('Failed to update profile.', 'danger');
      }
    }, 'json').fail(onError);
  });


  // ════════════════════ AUTOFOCUS IN MODALS ════════════════════

  $(document).on('shown.bs.modal', '.modal', function() {
    $(this).find('[autofocus]').focus();
  });

  // Clear cert gen output when modal reopened
  $(document).on('show.bs.modal', '#modal-cert-add', function() {
    $('#modal-cert-cn').val('');
    $('#cert-gen-output').addClass('d-none');
    $('#cert-gen-pre').text('');
  });

});
