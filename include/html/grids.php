<?php
function getHistory($cfg_file, $accordion_id, $open_first_history_tab = false) {
   ob_start(); ?>
   <h3>History</h3>
   <div class="panel-group" id="accordion<?= $accordion_id ?>">
      <?php foreach (array_reverse(glob('client-conf/'.basename(pathinfo($cfg_file, PATHINFO_DIRNAME)).'/history/*')) as $i => $file): ?>
         <div class="panel panel-default">
            <div class="panel-heading">
               <h4 class="panel-title">
                  <a data-toggle="collapse" data-parent="#accordion<?= $accordion_id ?>" href="#collapse<?= $accordion_id ?>-<?= $i ?>">
                     <?php
                     $history_file_name = basename($file);
                     $chunks = explode('_', $history_file_name);
                     printf('[%s] %s', date('r', $chunks[0]), $chunks[1]);
                     ?>
                  </a>
               </h4>
            </div>
            <div id="collapse<?= $accordion_id ?>-<?= $i ?>" class="panel-collapse collapse <?= $i===0 && $open_first_history_tab?'in':'' ?>">
               <div class="panel-body"><pre><?= file_get_contents($file) ?></pre></div>
            </div>
         </div>
      <?php endforeach; ?>
   </div><?php
   $history = ob_get_contents();
   ob_end_clean();
   return $history;
}
?>

<!-- Stats Panel (Option B) -->
<div class="stats-row" id="stats-row">
  <div class="stat-card">
    <div class="stat-value" id="stat-total-users">-</div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card stat-online">
    <div class="stat-value" id="stat-online-now">-</div>
    <div class="stat-label">Online Now</div>
  </div>
  <div class="stat-card stat-disabled">
    <div class="stat-value" id="stat-disabled">-</div>
    <div class="stat-label">Disabled</div>
  </div>
  <div class="stat-card stat-logs">
    <div class="stat-value" id="stat-log-entries">-</div>
    <div class="stat-label">Log Entries</div>
  </div>
</div>

<div class="tab-content">

   <div id="menu0" class="tab-pane fade in active">
      <!-- Users grid -->
      <div class="block-grid row" id="user-grid">
         <h4>
            OpenVPN Users <button data-toggle="modal" data-target="#modal-user-add" type="button" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-plus"></span></button>
         </h4>
         <table id="table-users" class="table"></table>

         <div id="modal-user-add" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                     <h4 class="modal-title">Add user</h4>
                  </div>
                  <div class="modal-body">
                     <div class="form-group">
                        <label for="modal-user-add-username">Username</label>
                        <input type="text" name="username" id="modal-user-add-username" class="form-control" autofocus/>
                     </div>
                     <div class="form-group">
                        <label for="modal-user-add-password">Password</label>
                        <input type="password" name="password" id="modal-user-add-password" class="form-control" />
                     </div>
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                     <button type="button" class="btn btn-primary" id="modal-user-add-save">Save</button>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div id="menu1" class="tab-pane fade">
      <!-- Logs grid -->
      <div class="block-grid row" id="log-grid">
         <h4>OpenVPN Logs</h4>
         <table id="table-logs" class="table" data-filter-control="true"></table>
      </div>
   </div>

   <div id="menu2" class="tab-pane fade">
      <!-- Admins grid -->
      <div class="block-grid row" id="admin-grid">
         <h4>
            Web Admins <button data-toggle="modal" data-target="#modal-admin-add" type="button" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-plus"></span></button>
         </h4>
         <table id="table-admins" class="table"></table>

         <div id="modal-admin-add" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
               <div class="modal-content">
                  <div class="modal-header">
                     <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                     <h4 class="modal-title">Add admin</h4>
                  </div>
                  <div class="modal-body">
                     <div class="form-group">
                        <label for="modal-admin-add-username">Username</label>
                        <input type="text" name="username" id="modal-admin-add-username" class="form-control" autofocus/>
                     </div>
                     <div class="form-group">
                        <label for="modal-admin-add-password">Password</label>
                        <input type="password" name="password" id="modal-admin-add-password" class="form-control" />
                     </div>
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                     <button type="button" class="btn btn-primary" id="modal-admin-add-save">Save</button>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <div id="menu3" class="tab-pane fade">
      <!-- configs -->
      <div class="block-grid row" id="config-cards">
         <ul class="nav nav-tabs nav-tabs-justified">
            <li><a data-toggle="tab" href="#menu-1-1"><span class="glyphicon glyphicon-th-large" aria-hidden="true"></span> Windows, Linux and OSX</a></li>
            <li id="save-config-btn" class="pull-right hidden"><a class="progress-bar-striped" href="javascript:;"><span class="glyphicon glyphicon-save" aria-hidden="true"></span></a></li>
         </ul>
         <div class="tab-content">
            <div id="menu-1-0" class="tab-pane fade in active">
               <textarea class="form-control" data-config-file="<?= $cfg_file='client-conf/gnu-linux/client.conf' ?>" name="" id="" cols="30" rows="20"><?= file_get_contents($cfg_file) ?></textarea>
               <?= getHistory($cfg_file, @++$accId) ?>
            </div>
            <div id="menu-1-1" class="tab-pane fade">
               <textarea class="form-control" data-config-file="<?= $cfg_file='client-conf/windows/client.ovpn' ?>" name="" id="" cols="30" rows="20"><?= file_get_contents($cfg_file) ?></textarea>
               <?= getHistory($cfg_file, ++$accId) ?>
            </div>
            <div id="menu-1-2" class="tab-pane fade">
               <textarea class="form-control" data-config-file="<?= $cfg_file='client-conf/osx-viscosity/client.conf' ?>" name="" id="" cols="30" rows="20"><?= file_get_contents($cfg_file) ?></textarea>
               <?= getHistory($cfg_file, ++$accId) ?>
            </div>
         </div>
      </div>
   </div>

   <div id="menu4" class="tab-pane fade">
      <!-- filename -->
      <div class="block-grid row" id="config-cards">
         <ul class="nav nav-tabs nav-tabs-justified">
            <li><a data-toggle="tab" href="#menu-1-0"><span class="glyphicon glyphicon-heart" aria-hidden="true"></span> Ovpn Filename (without .ovpn extension)</a></li>
            <li id="save-config-btn" class="pull-right hidden"><a class="progress-bar-striped" href="javascript:;"><span class="glyphicon glyphicon-save" aria-hidden="true"></span></a></li>
         </ul>
         <div class="tab-content">
            <div id="menu-1-0" class="tab-pane fade in active">
               <textarea class="form-control" data-config-file="<?= $cfg_file='client-conf/windows/filename' ?>" name="" id="" cols="30" rows="20"><?= file_get_contents($cfg_file) ?></textarea>
               <?= getHistory($cfg_file, @++$accId) ?>
            </div>
         </div>
      </div>
   </div>

</div>

<!-- Delete Confirm Modal (Option E) -->
<div id="modal-confirm-delete" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Confirm Delete</h4>
      </div>
      <div class="modal-body">
        <p>Delete <strong id="modal-confirm-delete-name"></strong>? This cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="modal-confirm-delete-ok">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Modal (Option E) -->
<div id="modal-reset-pass" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Reset Password</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="modal-reset-pass-input">New Password</label>
          <input type="password" id="modal-reset-pass-input" class="form-control" autofocus />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="modal-reset-pass-save">Reset</button>
      </div>
    </div>
  </div>
</div>

<script src="vendor/jquery/dist/jquery.min.js"></script>
<script src="vendor/bootstrap/js/modal.js"></script>
<script src="vendor/bootstrap/js/tooltip.js"></script>
<script src="vendor/bootstrap/js/tab.js"></script>
<script src="vendor/bootstrap/js/collapse.js"></script>
<script src="vendor/bootstrap/js/popover.js"></script>
<script src="vendor/bootstrap-table/dist/bootstrap-table.min.js"></script>
<script src="vendor/bootstrap-datepicker/dist/js/bootstrap-datepicker.js"></script>
<script src="vendor/bootstrap-table/dist/extensions/editable/bootstrap-table-editable.min.js"></script>
<script src="vendor/bootstrap-table/dist/extensions/filter-control/bootstrap-table-filter-control.min.js"></script>
<script src="vendor/x-editable/dist/bootstrap3-editable/js/bootstrap-editable.js"></script>
<script src="js/grids.js"></script>

<script>
$(document).ready(function(){
  // Update sidebar active state and URL hash when a tab is shown
  $('#admin-sidebar-nav a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
    $('#admin-sidebar-nav li').removeClass('active');
    $(this).parent().addClass('active');
    window.location.hash = $(this).attr('href').substr(1);
  });

  // Restore active tab from URL hash on page load
  var hash = window.location.hash;
  if (hash) {
    var $link = $('#admin-sidebar-nav a[href="' + hash + '"]');
    if ($link.length) {
      $('#admin-sidebar-nav li').removeClass('active');
      $link.parent().addClass('active');
      $link.tab('show');
    }
  }
});
</script>
