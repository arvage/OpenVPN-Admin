$(function () {
  "use strict";

  // ------------------------- GENERIC STUFF ------------------------------
  window.printStatus = function(msg, alert_type, bootstrap_icon) {
    alert_type = alert_type || 'warning';
    bootstrap_icon = bootstrap_icon || '';
    $('#message-stage').empty()
        .append(
           $(document.createElement('div'))
           .addClass('alert alert-'+alert_type)
           .html(bootstrap_icon?'<i class="stauts-icon glyphicon glyphicon-'+bootstrap_icon+'"></i>':'')
           .append(msg)
           .hide().fadeIn().delay(2000).fadeOut()
        );
  };

  // ------------------------- GLOBAL definitions -------------------------
  var gridsUrl = 'include/grids.php';

  function deleteFormatter() {
    return "<span class='glyphicon glyphicon-remove action'></span>";
  }

  function refreshTable($table) {
    $table.bootstrapTable('refresh');
  }

  function onAjaxError(xhr, textStatus, error) {
    console.error(error);
    printStatus('Error: ' + textStatus, 'danger', 'warning-sign');
  }

  // Option E: toggle switch instead of raw checkbox
  function toggleFormatter(value, row, index) {
    return '<label class="toggle-switch">' +
      '<input type="checkbox" ' + (parseInt(value) === 1 ? 'checked' : '') + ' />' +
      '<span class="toggle-slider"></span>' +
      '</label>';
  }

  // Option E: masked password + Reset button
  function passFormatter(value, row) {
    return '<span class="pass-mask">\u2022\u2022\u2022\u2022\u2022\u2022</span>' +
      '<button class="btn btn-xs btn-warning reset-pass-btn" data-user-id="' + row.user_id + '">Reset</button>';
  }

  function LEDIndicatorFormatter(value, row, index) {
    return '<div class="' + (parseInt(value) === 1 ? 'mini-led-green' : 'mini-led-red') + '"></div>';
  }

  function bytesStyle(value, row, index, field) {
    if (value.includes("KB") === true) {
      return {
        classes: 'text-nowrap another-class',
        css: {"background-color": "rgba(0, 100, 0, 0.3);"}
      };
    } else {
      return {
        classes: 'text-nowrap another-class',
        css: {"background-color": "rgba(100, 0, 0, 0.3);"}
      };
    }
  }

  // Option E: highlight rows where end_date is in the past
  function userRowStyle(row) {
    if (row.user_end_date) {
      var today = new Date().toISOString().split('T')[0];
      if (row.user_end_date < today) {
        return { classes: 'user-expired' };
      }
    }
    return {};
  }

  // ------------------------- USERS definitions -------------------------
  var $userTable = $('#table-users');
  var $modalUserAdd = $('#modal-user-add');
  var $userAddSave = $modalUserAdd.find('#modal-user-add-save');

  function addUser(username, password) {
    $.ajax({
      url: gridsUrl,
      method: 'POST',
      data: {
        add_user: true,
        user_id: username,
        user_pass: password
      },
      success: function() {
        refreshTable($userTable);
        loadStats();
      },
      error: onAjaxError
    });
  }

  function deleteUser(user_id) {
    $.ajax({
      url: gridsUrl,
      data: {
        del_user: true,
        del_user_id: user_id
      },
      method: 'POST',
      success: function() {
        refreshTable($userTable);
        loadStats();
      },
      error: onAjaxError
    });
  }

  function genericSetField(field, new_value, pk) {
    $.ajax({
      url: gridsUrl,
      data: {
        set_user: true,
        name: field,
        value: new_value,
        pk: pk
      },
      method: 'POST',
      success: function() {
        refreshTable($userTable);
        loadStats();
      },
      error: onAjaxError
    });
  }

  var userEditable = {
    url: gridsUrl,
    params: function (params) {
      params.set_user = true;
      return params;
    },
    success: function () {
      refreshTable($userTable);
    }
  };

  function updateConfig(config_file, config_content) {
    $.ajax({
      url: gridsUrl,
      data: {
        update_config: true,
        config_file: config_file,
        config_content: config_content
      },
      success: function(res) {
        printStatus(
          res.config_success ? 'Config Successfully updated!' : 'An error occured while trying to save the updated config.',
          res.config_success ? 'success' : 'danger',
          res.config_success ? 'ok' : 'warning-sign'
        );
      },
      dataType: 'json',
      method: 'POST',
      error: onAjaxError
    });
  }

  // ES 2015 so be prudent
  if (typeof Object.assign == 'function') {
    var userDateEditable = Object.assign({ type: 'date', placement: 'bottom' }, userEditable);
  } else {
    console.warn('Your browser does not support Object.assign. You will not be able to modify the date inputs.');
  }

  // ------------------------- ADMIN definitions -------------------------
  var $adminTable = $('#table-admins');
  var $modalAdminAdd = $('#modal-admin-add');
  var $adminAddSave = $modalAdminAdd.find('#modal-admin-add-save');

  function addAdmin(username, password) {
    $.ajax({
      url: gridsUrl,
      method: 'POST',
      data: {
        add_admin: true,
        admin_id: username,
        admin_pass: password
      },
      success: function() {
        refreshTable($adminTable);
      },
      error: onAjaxError
    });
  }

  function deleteAdmin(admin_id) {
    $.ajax({
      url: gridsUrl,
      data: {
        del_admin: true,
        del_admin_id: admin_id
      },
      method: 'POST',
      success: function() {
        refreshTable($adminTable);
      },
      error: onAjaxError
    });
  }

  var adminEditable = {
    url: gridsUrl,
    params: function (params) {
      params.set_admin = true;
      return params;
    },
    success: function () {
      refreshTable($adminTable);
    }
  };

  // ------------------------- LOGS definitions -------------------------
  var $logTable = $('#table-logs');

  // ------------------------- DELETE CONFIRM MODAL (Option E) -------------------------
  var $confirmModal = $('#modal-confirm-delete');
  var pendingDeleteType = null;
  var pendingDeleteId = null;

  function confirmDelete(type, id) {
    pendingDeleteType = type;
    pendingDeleteId = id;
    $('#modal-confirm-delete-name').text(id);
    $confirmModal.modal('show');
  }

  $('#modal-confirm-delete-ok').on('click', function() {
    if (pendingDeleteType === 'user') {
      deleteUser(pendingDeleteId);
    } else if (pendingDeleteType === 'admin') {
      deleteAdmin(pendingDeleteId);
    }
    $confirmModal.modal('hide');
    pendingDeleteType = null;
    pendingDeleteId = null;
  });

  // ------------------------- RESET PASSWORD MODAL (Option E) -------------------------
  var $resetPassModal = $('#modal-reset-pass');

  $(document).on('click', '.reset-pass-btn', function() {
    var userId = $(this).data('user-id');
    $resetPassModal.data('user-id', userId).modal('show');
    $('#modal-reset-pass-input').val('');
  });

  $('#modal-reset-pass-save').on('click', function() {
    var userId = $resetPassModal.data('user-id');
    var newPass = $('#modal-reset-pass-input').val();
    if (newPass) {
      genericSetField('user_pass', newPass, userId);
    }
    $resetPassModal.modal('hide');
  });

  // ------------------------- STATS (Option B) -------------------------
  function loadStats() {
    $.getJSON(gridsUrl, { select: 'stats' }, function(data) {
      $('#stat-total-users').text(data.total_users);
      $('#stat-online-now').text(data.online_now);
      $('#stat-disabled').text(data.disabled);
      $('#stat-log-entries').text(data.log_entries);
    });
  }

  loadStats();

  // -------------------- USERS --------------------

  $userTable.bootstrapTable({
    url: gridsUrl,
    sortable: false,
    checkboxHeader: false,
    rowStyle: userRowStyle,
    queryParams: function (params) {
      params.select = 'user';
      return params;
    },
    idField: 'user_id',
    columns: [
      { title: "ID", field: "user_id", editable: userEditable },
      { title: "Pass", field: "user_pass", formatter: passFormatter },
      { title: "Mail", field: "user_mail", editable: userEditable },
      { title: "Phone", field: "user_phone", editable: userEditable },
      {
        title: "Online",
        field: "user_online",
        formatter: LEDIndicatorFormatter
      },
      {
        title: "Enabled",
        field: "user_enable",
        formatter: toggleFormatter,
        events: {
          'change input': function (e, value, row) {
            genericSetField('user_enable', e.target.checked ? '1' : '0', row.user_id);
          }
        }
      },
      { title: "Start Date", field: "user_start_date", editable: userDateEditable },
      { title: "End Date", field: "user_end_date", editable: userDateEditable },
      {
        title: 'Delete',
        field: "user_del",
        formatter: deleteFormatter,
        events: {
          'click .glyphicon': function (e, value, row) {
            confirmDelete('user', row.user_id);
          }
        }
      }
    ]
  });

  $userAddSave.on('click', function () {
    var $usernameInput = $modalUserAdd.find('input[name=username]');
    var $passwordInput = $modalUserAdd.find('input[name=password]');
    addUser($usernameInput.val(), $passwordInput.val());
    $modalUserAdd.modal('hide');
  });


  // -------------------- ADMINS --------------------

  $adminTable.bootstrapTable({
    url: gridsUrl,
    sortable: false,
    queryParams: function (params) {
      params.select = 'admin';
      return params;
    },
    idField: 'admin_id',
    columns: [
      { title: "ID", field: "admin_id", editable: adminEditable },
      { title: "Pass", field: "admin_pass", editable: adminEditable },
      {
        title: 'Delete',
        field: "admin_del",
        formatter: deleteFormatter,
        events: {
          'click .glyphicon': function (e, value, row) {
            confirmDelete('admin', row.admin_id);
          }
        }
      }
    ]
  });

  $adminAddSave.on('click', function () {
    var $usernameInput = $modalAdminAdd.find('input[name=username]');
    var $passwordInput = $modalAdminAdd.find('input[name=password]');
    addAdmin($usernameInput.val(), $passwordInput.val());
    $modalAdminAdd.modal('hide');
  });

  // -------------------- LOGS --------------------

  $logTable.bootstrapTable({
    url: gridsUrl,
    sortable: false,
    sidePagination: 'server',
    pagination: true,
    queryParams: function (params) {
      params.select = 'log';
      return params;
    },
    columns: [
      { title: "Log ID", field: "log_id", align: "center" },
      { title: "User ID", field: "user_id", filterControl: 'select', align: "center" },
      { title: "Client IP", field: "log_trusted_ip", filterControl: 'select', align: "center" },
      { title: "Local IP", field: "log_remote_ip", filterControl: 'select', align: "center" },
      { title: "Start Time", field: "log_start_time", align: "center" },
      { title: "End Time", field: "log_end_time", align: "center" },
      { title: "Received", field: "log_received", align: "center", cellStyle: bytesStyle },
      { title: "Sent", field: "log_send", align: "center", cellStyle: bytesStyle }
    ]
  });

  // watch the config textareas for changes and persist them if a change was made
  $('textarea').keyup(function() {
    $('#save-config-btn').removeClass('saved-success hidden').addClass('get-attention');
  }).change(function() {
    updateConfig($(this).data('config-file'), $(this).val());
    $('#save-config-btn').removeClass('get-attention').addClass('saved-success');
  });

}); // doc ready end

// -------------------- HACKS --------------------

// Autofocus for bootstrap modals
$(document).on('shown.bs.modal', '.modal', function() {
  $(this).find('[autofocus]').focus();
});
