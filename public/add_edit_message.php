<?php

/* interface/modules/custom_modules/oe-module-custom-sms-reminders/public/add_edit_message.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    David Hantke
 * @copyright Copyright (c) 2022 David Hantke
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../../../../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

// gacl control
$thisauthview = AclMain::aclCheckCore('admin', 'admin', false, 'view');
$thisauthwrite = AclMain::aclCheckCore('admin', 'admin', false, 'write');

if (!($thisauthwrite || $thisauthview)) {
    echo "<html>\n<body>\n";
    echo "<p>" . xlt('You are not authorized for this.') . "</p>\n";
    echo "</body>\n</html>\n";
    exit();
}

// Translation for form fields.
function ffescape($field)
{
    $field = add_escape_custom($field);
    return trim($field);
}

$alertmsg = '';
$mode = $_POST['mode'] ?? null;
$id = 0; // DRH needed or not?
$activity = '1'; // DRH was 1
$language = '';
$message = '';

if (isset($mode) && $thisauthwrite) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }

    $id    = empty($_POST['id']) ? '' : $_POST['id'] + 0;
    $language   = $_POST['language'];
    $message    = $_POST['message'];
    $activity     = empty($_POST['activity']) ? 0 : 1;

    if ($mode == "delete") {
        sqlStatement("DELETE FROM mod_custom_sms_reminders WHERE id = ?", array($id));
        $id = 0;
    } elseif ($mode == "add") {
        $crow = sqlQuery("SELECT COUNT(*) AS count FROM mod_custom_sms_reminders WHERE " .
            "language = '"    . ffescape($language)    . "' AND " .
            "activity = 1");
        if ($crow['count'] && $_POST['activity'] == 1) {
            $alertmsg = xl('Cannot add/update this entry because there can be only one active ' .
                'message per language! If updating, try deactivating the message temporarily in '.
                'order to update.');
        } else {
            $sql =
                "language = '"         . ffescape($language)         . "', " .
                "message = '"    . ffescape($message)    . "', " .
                "activity = '"    . add_escape_custom($activity) . "' ";
            if ($id) { // it's an update
                $query = "UPDATE mod_custom_sms_reminders SET $sql WHERE id = ?";
                sqlStatement($query, array($id));
            } else { // it's an addition
                $id = sqlInsert("INSERT INTO mod_custom_sms_reminders SET $sql");
            }

            if (!$alertmsg) {
                $language = $message = "";
                $id = 0;
                $activity = 1;
            }
        }
    } elseif ($mode == "edit") { // someone clicked [Edit]
        $sql = "SELECT * FROM mod_custom_sms_reminders WHERE id = ?";
        $results = sqlStatement($sql, array($id));
        while ($row = sqlFetchArray($results)) {
            $id          = $row['id'];
            $language    = $row['language'];
            $message     = $row['message'];
            $activity    = 0 + $row['activity'];
        }
    }
}

$search = $_REQUEST['search'] ?? null;
$search_active = $_REQUEST['search_active'] ?? null;

?>

<html>
<head>
    <title><?php echo xlt("Messages"); ?></title>

    <?php Header::setupHeader(['select2']); ?>

<style>
    .ui-autocomplete {
      max-height: 350px;
      max-width: 35%;
      overflow-y: auto;
      overflow-x: hidden;
    }
</style>

<script>

function submitAdd() {
    var f = document.forms[0];
    //if (!validEntry(f)) return;
    f.mode.value = 'add';
    f.id.value = '';
    f.submit();
}

function submitUpdate() {
    var f = document.forms[0];
    if (! parseInt(f.id.value)) {
        alert(<?php echo xlj('Cannot update because you are not editing an existing entry!'); ?>);
        return;
    }
    //if (!validEntry(f)) return;
    f.mode.value = 'add';
    f.submit();
}

function submitEdit(id) {
    var f = document.forms[0];
    f.mode.value = 'edit';
    f.id.value = id;
    f.submit();
}

function submitDelete(id) {
    var f = document.forms[0];
    f.mode.value = 'delete';
    f.id.value = id;
    f.submit();
}

</script>

</head>
<body class="body_top">
<form method='post' action='add_edit_message.php' name='theform'>
  <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
  <input type='hidden' name='mode' value='' />
  <br />
  <div class="container">
    <p>Add/edit SMS message (limited wildcards available for NAME, INITS, PROVIDER, DATE, STARTTIME, ENDTIME)</p>
    <div class="form-group row">
      <label class="col-form-label"><?php echo xlt('Language'); ?>:</label>
      <div class="col-md">
        <input type='text' class='form-control form-control-sm' size='6' name='language' value='<?php echo attr($language ?? '') ?>' />
      </div>
      <label class="col-form-label"><?php echo xlt('Message'); ?>:</label>
      <div class="col-md">
        <input type='text' class='form-control form-control-sm' size='6' name='message' value='<?php echo attr($message ?? '') ?>' />
      </div>
      <div class="col-md">
        <input type='checkbox' name='activity' value='1'<?php if (!empty($activity) || ($mode == 'modify' && $activity == null)) {
          echo ' checked'; } ?> />
        <?php echo xlt('Active'); ?>
      </div>
    </div> <!-- end div class="form-group row" -->
    <input type="hidden" name="id" value="<?php echo attr($id) ?>" />

    <?php if ($thisauthwrite) { ?>
      <p class="text-center">
      <a href='javascript:submitUpdate();' class='link'>[<?php echo xlt('Update'); ?>]</a>
        &nbsp;&nbsp;
      <a href='javascript:submitAdd();' class='link'>[<?php echo xlt('Add as New'); ?>]</a>
      </p>
    <?php } ?>
  </div> <!-- end div class="container" -->
  <hr>
  <div class="container-fluid">
    <div class="row align-items-end">
      <div class="col-md">
        <input type="text" name="search" class="form-control form-control-sm" size="5" value="<?php echo attr($search) ?>" />
      </div>
      <div class="col-md">
        <input type="submit" class="btn btn-primary btn-sm" name="go" value='<?php echo xla('Search Language'); ?>' />
      </div>
      <div class="col-md">
        <input type='checkbox' title='<?php echo xla("Only Show Active Messages ") ?>' name='search_active' value='1'<?php if (!empty($search_active)) {
          echo ' checked'; } ?> /><?php echo xlt('Active Messages'); ?>
      </div>
    </div> <!-- end div class="row align-items-end" -->
  </div> <!-- end div class="container-fluid"> -->
</form>

<table class='table table-borderless' cellpadding='5' cellspacing='0'>
  <tr>
    <td><span class='font-weight-bold'><?php echo xlt('Language'); ?></span></td>
    <td><span class='font-weight-bold'><?php echo xlt('Message'); ?></span></td>
    <td><span class='font-weight-bold'><?php echo xlt('Active'); ?></span></td>
    <td></td>
    <td></td>
  </tr>

  <?php
  $res = sqlStatement("SELECT * FROM mod_custom_sms_reminders WHERE language = ?", array($_POST['search']));
  for ($i = 0; $row = sqlFetchArray($res); $i++) {
    $all[$i] = $row;
  }

  if (!empty($all)) {
    foreach ($all as $iter) {
      echo " <tr>\n";
      echo "  <td class='text'>" . text($iter["language"]) . "</td>\n";
      echo "  <td class='text'>" . text($iter["message"]) . "</td>\n";
      echo "  <td class='text'>" . ( ($iter["activity"] == 1) ? xlt('Yes') : xlt('No')) . "</td>\n";
      if ($thisauthwrite) {
        echo "  <td class='text-right'><a class='link' href='javascript:submitDelete(" . attr_js($iter['id']) . ")'>[" . xlt('Delete') . "]</a></td>\n";
        echo "  <td class='text-right'><a class='link' href='javascript:submitEdit(" . attr_js($iter['id']) . ")'>[" . xlt('Edit') . "]</a></td>\n";
      }
      echo " </tr>\n";
    }
  }
  ?>
</table>

<script>
    <?php
    if ($alertmsg) {
        echo "alert(" . js_escape($alertmsg) . ");\n";
    }
    ?>
</script>

</body>
</html>
