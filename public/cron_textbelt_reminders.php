<?php
/* interface/modules/custom_modules/oe-module-custom-sms-reminders/public/cron_textbelt_reminders.php
 *
 * DRH 1/17/22 patterned after original cron_sms_notification.php file and modified as necessary.
 * Either comment out cron_sendSMS() on line 71 or add "_test" to end of
 * notification_settings.SMS_gateway_apikey to prevent message from actually being sent.
 * Also consider remarking out line 75 cron_updateentry for testing in order to avoid
 * setting the openemr_postcalendar_events.sendalertsms flag to TRUE, which would prevent a
 * message from being sent.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    David Hantke
 * @copyright Copyright (c) 2022 David Hantke
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

 // Both 'localhost' and '<IP_Address>' work. This seemingly needs to be hardcoded as it's outside the scope of the webserver (it's run by cron).
$_SERVER['HTTP_HOST'] = 'localhost';
$ignoreAuth = 1; // Somehow needed or script won't run secondary to lack of siteID

// Need to construct path to required files. OEMR global variables don't work as this script is run outside of OpenEMR.
// Therefore, construct module such that options are saved to a text file and then harvest that info to an array ($csv).
// $csv[0] -> "1" if enabled, "" if not
// $csv[1] -> gateway_apikey
// $csv[2] -> SMS send before hours
// $csv[3] -> openemr installation directory (i.e., /var/www/html/openemr)

include_once($csv[3] . "/interface/globals.php");
include_once($csv[3] . "/library/sql.inc");
include_once($csv[3] . "/interface/modules/custom_modules/oe-module-custom-sms-reminders/public/cron_textbelt_functions.php");
include_once($csv[3] . "/interface/modules/custom_modules/oe-module-custom-sms-reminders/public/sms_textbelt.php");

// object for sms
global $mysms;
global $SMS_GATEWAY_APIKEY;
global $SMS_SEND_BEFORE_HOURS;

$SMS_GATEWAY_APIKEY = $csv[1];
$SMS_SEND_BEFORE_HOURS = $csv[2];

// create sms object (DRH defined in sms_textbelt.php, necessary for access to methods contained therein)
$mysms = new sms($SMS_GATEWAY_APIKEY);
$db_patient = cron_getAlertpatientData($SMS_SEND_BEFORE_HOURS); // returns patients eligible to receive reminders and name of their doctor
// echo $db_patient[0]['lname'] . "\n";

if ( !empty($db_patient) && $csv[0] == '1' ) {
	// for every event found
	for ($p = 0; $p < count($db_patient); $p++) {
		sleep(1); // textbelt suggests only one message per second
 		$prow = $db_patient[$p];
 		// echo $prow['lname'] . "\n";

 		$app_date = $prow['pc_eventDate'] . " " . $prow['pc_startTime'];
 		$app_time = strtotime($app_date);

 		$app_time_hour = round($app_time / 3600);
 		$curr_total_hour = round(time() / 3600);

 		$remaining_app_hour = round($app_time_hour - $curr_total_hour);
 		$remain_hour = round($remaining_app_hour - $SMS_SEND_BEFORE_HOURS);

		if ($remain_hour >= -($SMS_SEND_BEFORE_HOURS) && $remain_hour <= $SMS_SEND_BEFORE_HOURS) {
 			// insert entry in notification_log table
 			$strMsg = cron_InsertNotificationLogEntry($prow);
                        // echo $strMsg . "\n";

 			// *************************************************************************************************
			// Sends sms to patient, REMARK OUT next line for testing (or add "_test" to apikey). UNREMARK to
			// actually send a message.
			cron_SendSMS($prow['phone_cell'], $strMsg);

 			// *************************************************************************************************
 			// Sets openemr_postcalendar_events.pc_sendalertsms to 'YES' to prevent further transmissions of SMS.
 			// For production setups this allows only one message to be sent per patient. For testing, however,
 			// it's inconvenient to keep resetting pc_sendalertsms from 'NO' to 'YES' so keep this remarked out.
 			// You'll have unhappy patients if you keep texting them every hour so...
 			// REMARK OUT FOR TESTING! UNREMARK FOR PRODUCTION USE!
 			cron_updateentry($prow['pid'], $prow['pc_eid']);
		}
 		unset($csv);
	}
}
unset($mysms);
?>
