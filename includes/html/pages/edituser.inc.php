<?php

use App\Models\User;

$no_refresh = true;

echo "<div style='margin: 10px;'>";

$pagetitle[] = 'Edit user';

if (! Auth::user()->hasGlobalAdmin()) {
    include 'includes/html/error-no-perm.inc.php';
} else {
    if ($vars['user_id'] && empty($vars['edit'])) {
        $action = $vars['action'] ?? '';
        /** @var User $user */
        $user = User::find($vars['user_id']);
        $user_data = $user->toArray(); // for compatibility with current code

        echo '<p><h2>' . $user_data['realname'] . '</h2></p>';
        // Perform actions if requested
        if ($action == 'deldevperm') {
            if (dbFetchCell('SELECT COUNT(*) FROM devices_perms WHERE `device_id` = ? AND `user_id` = ?', [$vars['device_id'], $user_data['user_id']])) {
                dbDelete('devices_perms', '`device_id` =  ? AND `user_id` = ?', [$vars['device_id'], $user_data['user_id']]);
            }
        }

        if ($action == 'adddevperm') {
            if (! dbFetchCell('SELECT COUNT(*) FROM devices_perms WHERE `device_id` = ? AND `user_id` = ?', [$vars['device_id'], $user_data['user_id']])) {
                dbInsert(['device_id' => $vars['device_id'], 'user_id' => $user_data['user_id']], 'devices_perms');
            }
        }

        if ($action == 'deldevgroupperm') {
            $user->deviceGroups()->detach($vars['device_group_id']);
        }

        if ($action == 'adddevgroupperm') {
            $user->deviceGroups()->syncWithoutDetaching($vars['device_group_id']);
        }

        if ($action == 'delifperm') {
            if (dbFetchCell('SELECT COUNT(*) FROM ports_perms WHERE `port_id` = ? AND `user_id` = ?', [$vars['port_id'], $user_data['user_id']])) {
                dbDelete('ports_perms', '`port_id` =  ? AND `user_id` = ?', [$vars['port_id'], $user_data['user_id']]);
            }
        }

        if ($action == 'addifperm') {
            if (! dbFetchCell('SELECT COUNT(*) FROM ports_perms WHERE `port_id` = ? AND `user_id` = ?', [$vars['port_id'], $user_data['user_id']])) {
                dbInsert(['port_id' => $vars['port_id'], 'user_id' => $user_data['user_id']], 'ports_perms');
            }
        }

        if ($action == 'delbillperm') {
            if (dbFetchCell('SELECT COUNT(*) FROM bill_perms WHERE `bill_id` = ? AND `user_id` = ?', [$vars['bill_id'], $user_data['user_id']])) {
                dbDelete('bill_perms', '`bill_id` =  ? AND `user_id` = ?', [$vars['bill_id'], $user_data['user_id']]);
            }
        }

        if ($action == 'addbillperm') {
            if (! dbFetchCell('SELECT COUNT(*) FROM bill_perms WHERE `bill_id` = ? AND `user_id` = ?', [$vars['bill_id'], $user_data['user_id']])) {
                dbInsert(['bill_id' => $vars['bill_id'], 'user_id' => $user_data['user_id']], 'bill_perms');
            }
        }

        echo '<div class="row">
           <div class="col-md-4">';

        // Display devices this users has access to
        echo '<h3>Device Access</h3>';

        echo "<div class='panel panel-default panel-condensed'>
            <table class='table table-hover table-condensed table-striped'>
              <tr>
                <th>Device</th>
                <th>Action</th>
              </tr>";

        $device_perms = dbFetchRows('SELECT * from devices_perms as P, devices as D WHERE `user_id` = ? AND D.device_id = P.device_id', [$user_data['user_id']]);
        foreach ($device_perms as $device_perm) {
            echo '<tr><td><strong>' . htmlentities(format_hostname($device_perm)) . "</td><td> <a href='edituser/action=deldevperm/user_id=" . $vars['user_id'] . '/device_id=' . $device_perm['device_id'] . "'><i class='fa fa-trash fa-lg icon-theme' aria-hidden='true'></i></a></strong></td></tr>";
            $access_list[] = $device_perm['device_id'];
            $permdone = 'yes';
        }

        echo '</table>
          </div>';

        if (empty($permdone)) {
            echo 'None Configured';
        }

        // Display devices this user doesn't have access to
        echo '<h4>Grant access to new device</h4>';
        echo "<form class='form-inline' role='form' method='post' action=''>
            " . csrf_field() . "
            <input type='hidden' value='" . $user_data['user_id'] . "' name='user_id'>
            <input type='hidden' value='edituser' name='page'>
            <input type='hidden' value='adddevperm' name='action'>
            <div class='form-group'>
              <label class='sr-only' for='device_id'>Device</label>
              <select name='device_id' id='device_id' class='form-control'>
              </select>
           </div>
           <button type='submit' class='btn btn-default' name='Submit'>Add</button></form>";

        echo '</div>
           <div class="col-md-4">';

        // Display devices this users has access to
        echo '<h3>Device access via Device Group (beta)</h3>';

        echo "<div class='panel panel-default panel-condensed'>
            <table class='table table-hover table-condensed table-striped'>
              <tr>
                <th>Device Group</th>
                <th>Action</th>
              </tr>";

        foreach ($user->deviceGroups as $device_group_perm) {
            echo '<tr><td><strong>' . $device_group_perm->name . "</td><td> <a href='edituser/action=deldevgroupperm/user_id=" . $user->user_id . '/device_group_id=' . $device_group_perm->id . "'><i class='fa fa-trash fa-lg icon-theme' aria-hidden='true'></i></a></strong></td></tr>";
        }

        echo '</table>
          </div>';

        if ($user->deviceGroups->isEmpty()) {
            echo 'None Configured';
        }

        // Display device groups this user doesn't have access to
        echo '<h4>Grant access to new Device Group</h4>';
        $allow_dynamic = \App\Facades\LibrenmsConfig::get('permission.device_group.allow_dynamic');
        if (! $allow_dynamic) {
            echo '<i>Dynamic groups are disabled, set permission.device_group.allow_dynamic to enable.</i>';
        }

        echo "<form class='form-inline' role='form' method='post' action=''>
            " . csrf_field() . "
            <input type='hidden' value='" . $user_data['user_id'] . "' name='user_id'>
            <input type='hidden' value='edituser' name='page'>
            <input type='hidden' value='adddevgroupperm' name='action'>
            <div class='form-group'>
              <label class='sr-only' for='device_group_id'>Device</label>
              <select name='device_group_id' id='device_group_id' class='form-control'></select>
           </div>
           <button type='submit' class='btn btn-default' name='Submit'>Add</button></form>";

        echo "</div></div>

        <div class='row'>
          <div class='col-md-4'>";
        echo '<h3>Interface Access</h3>';

        $interface_perms = dbFetchRows('SELECT * from ports_perms as P, ports as I, devices as D WHERE `user_id` = ? AND I.port_id = P.port_id AND D.device_id = I.device_id', [$user_data['user_id']]);

        echo "<div class='panel panel-default panel-condensed'>
            <table class='table table-hover table-condensed table-striped'>
              <tr>
                <th>Interface name</th>
                <th>Action</th>
              </tr>";
        foreach ($interface_perms as $interface_perm) {
            echo '<tr>
              <td>
                <strong>' . $interface_perm['hostname'] . ' - ' . $interface_perm['ifDescr'] . '</strong>' . '' . \LibreNMS\Util\Clean::html($interface_perm['ifAlias'], []) . "
              </td>
              <td>
                &nbsp;&nbsp;<a href='edituser/action=delifperm/user_id=" . $user_data['user_id'] . '/port_id=' . $interface_perm['port_id'] . "'><i class='fa fa-trash fa-lg icon-theme' aria-hidden='true'></i></a>
              </td>
            </tr>";
            $ipermdone = 'yes';
        }

        echo '</table>
          </div>';

        if (empty($ipermdone)) {
            echo 'None Configured';
        }

        // Display interfaces this user doesn't have access to
        echo '<h4>Grant access to new interface</h4>';

        echo "<form action='' method='post' class='form-horizontal' role='form'>
        " . csrf_field() . "
        <input type='hidden' value='" . $user_data['user_id'] . "' name='user_id'>
        <input type='hidden' value='edituser' name='page'>
        <input type='hidden' value='addifperm' name='action'>
        <div class='form-group'>
          <label for='device' class='col-sm-2 control-label'>Device: </label>
          <div class='col-sm-10'>
            <select id='device' class='form-control' name='device' onchange='window.port_device_id = this.value; $(\"#port_id\").empty().trigger(\"change\");'></select>
          </div>
          </div>
          <div class='form-group'>
            <label for='port_id' class='col-sm-2 control-label'>Interface: </label>
            <div class='col-sm-10'>
              <select class='form-control' id='port_id' name='port_id'></select>
            </div>
         </div>
         <div class='form-group'>
           <div class='col-sm-12'>
             <button type='submit' class='btn btn-default' name='Submit' value='Add'>Add</button>
           </div>
         </div>
       </form>";

        echo "</div>
          <div class='col-md-4'>";
        echo '<h3>Bill Access</h3>';

        $bill_perms = dbFetchRows('SELECT * from bills AS B, bill_perms AS P WHERE P.user_id = ? AND P.bill_id = B.bill_id', [$user_data['user_id']]);

        echo "<div class='panel panel-default panel-condensed'>
            <table class='table table-hover table-condensed table-striped'>
            <tr>
              <th>Bill name</th>
              <th>Action</th>
            </tr>";

        foreach ($bill_perms as $bill_perm) {
            echo '<tr>
              <td>
                <strong>' . htmlentities($bill_perm['bill_name']) . "</strong></td><td width=50>&nbsp;&nbsp;<a href='edituser/action=delbillperm/user_id=" . $vars['user_id'] . '/bill_id=' . $bill_perm['bill_id'] . "'><i class='fa fa-trash fa-lg icon-theme' aria-hidden='true'></i></a>
              </td>
            </tr>";
            $bill_access_list[] = $bill_perm['bill_id'];

            $bpermdone = 'yes';
        }

        echo '</table>
          </div>';

        if (empty($bpermdone)) {
            echo 'None Configured';
        }

        // Display devices this user doesn't have access to
        echo '<h4>Grant access to new bill</h4>';
        echo "<form method='post' action='' class='form-inline' role='form'>
            " . csrf_field() . "
            <input type='hidden' value='" . $user_data['user_id'] . "' name='user_id'>
            <input type='hidden' value='edituser' name='page'>
            <input type='hidden' value='addbillperm' name='action'>
            <div class='form-group'>
              <label class='sr-only' for='bill_id'>Bill</label>
              <select name='bill_id' class='form-control' id='bill_id'></select>
          </div>
          <button type='submit' class='btn btn-default' name='Submit' value='Add'>Add</button>
        </form>
        <script>
          init_select2('#device_id', 'device', {'user': " . $user->user_id . ", 'access': 'inverted'}, null, 'Select Device');
          init_select2('#device', 'device', {'user': " . $user->user_id . "}, null, 'Select Device');
          window.port_device_id = null;
          init_select2('#port_id', 'port', function(params) {
                return {
                    term: params.term,
                    page: params.page || 1,
                    device: window.port_device_id
                };
            });
          init_select2('#device_group_id', 'device-group', {" . ($allow_dynamic ? '' : '"type": "static"') . "}, null, 'Select Group');
          init_select2('#bill_id', 'bill', {}, null, 'Select Bill');
        </script>
        </div>";
    } else {
        echo '<script>window.location.replace("' . url('users') . '");</script>';
    }//end if
}//end if

echo '</div>';
