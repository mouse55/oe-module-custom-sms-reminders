<?php

/* interface/modules/custom_modules/oe-module-custom-sms-reminders/public/cron_textbelt_functions.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    David Hantke
 * @copyright Copyright (c) 2022 David Hantke
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

global $smsgateway_info;
global $patient_info;
global $data_info;

/*
 * Function:    cron_SendSMS
 * Purpose: 	calls sms_textbelt.send() to actually send the sms
 */
function cron_SendSMS($to, $vBody)
{
    global $mysms;

    $mstatus = true; // DRH is this needed?
    $mysms->send($to, $vBody);
    return $mstatus;
}

/*
 * Function:    cron_updateentry
 * Purpose: 	update openemr_postcalendar_events.pc_sendalertmsg status to yes if
 *				alert sent to patient
 */
function cron_updateentry($pid, $pc_eid)
{
    $query  = "UPDATE openemr_postcalendar_events SET pc_sendalertsms='YES' ";
    $query .= "WHERE pc_pid=? AND pc_eid=?";

    $db_sql = (sqlStatement($query, [$pid, $pc_eid]));
}

/*
 * Function:    cron_getAlertpatientData
 * Purpose: 	get patient data for sending to alert
 */
function cron_getAlertpatientData($SMS_SEND_BEFORE_HOURS)
{
    $check_date = date("Y-m-d", mktime(date("h") + $SMS_SEND_BEFORE_HOURS, 0, 0, date("m"), date("d"), date("Y"))); // DRH was SMS_NOTIFICATION_HOUR
    $ssql = "pd.allow_sms_reminders='YES' AND pd.phone_cell<>'' AND ope.pc_sendalertsms='NO' ";
    $ssql .= "AND ope.pc_eventDate>='" . date("Y-m-d") . "' AND ope.pc_eventDate<='" . add_escape_custom($check_date) . "'"; // dates between current date and one week hence
    $patient_field = "pd.pid, pd.title, pd.fname, pd.lname, pd.mname, pd.language, pd.phone_cell,pd.email, pd.allow_sms_reminders,";

    $query = "SELECT $patient_field ope.pc_eid, ope.pc_pid, ope.pc_title, ope.pc_hometext, ope.pc_eventDate, ope.pc_endDate,
    		      ope.pc_duration, ope.pc_alldayevent, ope.pc_startTime, ope.pc_endTime, CONCAT('Dr. ', u.lname) as provider_name
			  FROM openemr_postcalendar_events AS ope
			  INNER JOIN patient_data AS pd ON ope.pc_pid = pd.pid
			  INNER JOIN users AS u ON ope.pc_aid = u.id
			  WHERE $ssql
			  ORDER BY ope.pc_eventDate, ope.pc_endDate, pd.pid"; // consider adding u.degree

    $db_patient = sqlStatement($query);
    $patient_array = array();
    $cnt = 0;
    while ($prow = sqlFetchArray($db_patient)) {
        $patient_array[$cnt] = $prow;
        $cnt++;
    }
    return $patient_array;
}

/*
 * Function:    cron_InsertNotificationLogEntry
 * Purpose: 	insert log entry in table
 * Returns:     message with textual replacements
 */
function cron_InsertNotificationLogEntry($prow)
{
    global $SMS_GATEWAY_APIKEY;
    $smsgateway_info = "SMS/TEXTBELT " . $SMS_GATEWAY_APIKEY;
    if ($prow['title']) {
        $patient_info = $prow['title'] . " ";
    }
    $patient_info .= $prow['fname'] . " ";
    if ($prow['mname']) {
        $patient_info .= $prow['mname'] . " ";
    }
    $patient_info .= $prow['lname'] . " " . $prow['phone_cell'];
    $data_info = $prow['pc_eventDate'] . "|||" . $prow['pc_startTime']; // isn't actually used

    $sql_loginsert = "INSERT INTO `notification_log` (`iLogId`, `pid`, `pc_eid`, " .
    	"`sms_gateway_type`, `message`, `email_sender`, `email_subject`, `type`, " .
    	"`patient_info`, `smsgateway_info`, `pc_eventDate`, `pc_endDate`, `pc_startTime`, " .
    	"`pc_endTime`, `dSentDateTime`) " .
    	"VALUES (NULL , ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $message = cron_getmessage($prow);
    $replaced_message = cron_setmessage($prow, $message);
    sqlStatement($sql_loginsert, [$prow['pid'], $prow['pc_eid'], 'SMS', $replaced_message,
        $prow['provider_name'], '', '', $patient_info, $smsgateway_info, $prow['pc_eventDate'],
        $prow['pc_endDate'], $prow['pc_startTime'], $prow['pc_endTime'],
        date("Y-m-d H:i:s")]);
    return $replaced_message;
}

/* Function:    cron_getmessage
 * Purpose:     returns base sms message in language of choice from text file
 * Returns:     language-spcified message (still with wild-cards if present)
 */
function cron_getmessage($prow) // added $pid to get patient language
{
    // get language
    $sql = "SELECT language FROM patient_data WHERE pid = ?";
    $res = sqlStatementNoLog($sql, array($prow['pid']));
    $row = sqlFetchArray($res); // English
    $pt_lang = strtolower($row['language']); // english

    if (empty($pt_lang)) { $pt_lang = 'english'; } // default to english if empty

    // create associative array with key == language and value == message
//     $messages = array();
//     if (($handle = fopen('messages.txt', 'r')) !== FALSE)
//     {
//         while (($mrow = fgetcsv($handle, 0, ',')) !== FALSE)
//         {
//             $messages[$mrow[0]] = $mrow[1];
//         }
//         fclose($handle);
//     }

    $sql = "SELECT message FROM mod_custom_sms_reminders WHERE language = ? AND activity = 1";
    $res = sqlStatementNoLog($sql, array($pt_lang));
    $row = sqlFetchArray($res);

//    return $messages[$pt_lang];
    return $row['message'];
}

/* Function:    cron_setmessage
 * Purpose: 	sets the message, substituting wildcard text where possible
 * Returns:		properly formatted message
 */
function cron_setmessage($prow, $message)
{
    // define what will become the wildcards
	global $NAME;
	$NAME = $prow['fname'] . " " . $prow['lname'];
	$INITS = strtoupper(substr($prow['fname'],0,1) . substr($prow['mname'],0,1) . substr($prow['lname'],0,1));
	$PROVIDER = $prow['provider_name'];
	$dtWrk = strtotime($prow['pc_eventDate'] . ' ' . $prow['pc_startTime']);
	$DATE = date('D n/j/y', $dtWrk);
	$STARTTIME = date('g:i A', $dtWrk);
	$ENDTIME = $prow['pc_endTime'];

	// assign previously defined text to each wildcard
	$find_array = array("***NAME***","***INITS***","***PROVIDER***","***DATE***","***STARTTIME***","***ENDTIME***");
	$replace_array = array($NAME,$INITS,$PROVIDER,$DATE,$STARTTIME,$ENDTIME);

    // actually replace the wildcard with the desired text
    $message = str_replace($find_array, $replace_array, $message);
    unset($find_array);
    unset($replace_array);

    // Now replace the contents of the newly expanded wildcards with an appropriate translation.
    // Currently this only translates days of the week into suitable abbreviations however by modifying
    // both the previous wildcard sections of this function as well as adding appropriate translations into
    // the lang_constants and lang_definitions tables you should be able to customize this as you want.

    // Set language id array, languages and ids are listed in the lang_languages table as well.
    // If you need another language you can add it both here and in the lang_languages table but
    // you'll then have to set up your translations in the tables as well. Otherwise everything
    // will simply default to English (Standard).

    // Note: this applies only to translation of the wildcards. If you've set up your base messages in
    // messages.txt as described in the Readme.md file the majority of the message will still be
    // transmitted in the language in which you entered it.
    $lang_ids = [
        "english" => 1, // (Standard 1, Indian 16, Autralian 36)),\
        "swedidh" =>  2,
        "spanish" =>  4, // (Spanish (Latin American) 4, Spanish(Spain 3))
        "german" => 5,
        "dutch" => 6,
        "hebrew" => 7,
        "french" => 8, // (Standard 8, Canadian 9)
        "chinese" => 10, // (Chinese (Simplified) 10, Chinese (Traditional) 13)
        "russian" => 12,
        "armenian" => 13,
        "bahasa" => 14,
        "greek" => 15,
        "portuguese" => 16, // (Portuguese (Portugal) 16, (Brazilian) 17, (Angolan) 18)
        "arabic" => 20,
        "danish" => 21,
        "turkish" => 22,
        "polish" => 23,
        "italian" => 24,
        "hindi" => 25,
        "romanian" => 26,
        "vietnamese" => 27,
        "albanian" => 28,
        "czech" => 29,
        "ukrainian" => 30,
        "persian" => 31,
        "japanese" => 32,
        "finnish" => 33,
        "marathi" => 34,
        "tamil" => 35,
        "dummy" => 37
    ];

    $lang = $prow['language'];
    $lang_id = $lang_ids[$prow['language']]; // assign the language a proper id number
	if ( ($lang_id != 1 || $lang_id != 16 || $lang_id != 36) && array_key_exists($lang, $lang_ids)) {
	    // Language is not English (or a variant) and it is in the $lang_ids list of available translations.
		// Spaces before and after words are to minimize chances of finding the string within a word in the message.
		$find_array = array(" Sun ", " Mon ", " Tue ", " Wed ", " Thu ", " Fri ", " Sat ");
		$replace_array = array(" ".xl_wc("Sun",$lang_id)." ", " ".xl_wc("Mon",$lang_id)." ",
		    " ".xl_wc("Tue", $lang_id)." ", " ".xl_wc("Wed", $lang_id)." ", " ".xl_wc("Thu", $lang_id)." ",
		     " ".xl_wc("Fri", $lang_id)." ", " ".xl_wc("Sat", $lang_id)." ");
    }
    $message = str_replace($find_array, $replace_array, $message);
    //echo $message . "\n"; // uncomment for command line testing to see outgoing message
    return $message;
}

/*
 * Function:    xl_wc (translate wildcard)
 * Purpose:     Translate contents of wildcard strings into desired language (if such translations
 *              exist within the system). Otherwise the wildcard string will be rendered in the
 *              OpenEMR default language.
 * Returns:     Translated string (or original one if a translation doesn't exist)
 */
function xl_wc($constant, $lang_id) // used to translate contents of wildcards to desired language (days of week, etc.)
{
    $sql = "SELECT ld.definition FROM lang_definitions AS ld
                INNER JOIN lang_constants AS lc ON lc.cons_id = ld.cons_id
                INNER JOIN lang_languages AS ll ON ll.lang_id=ld.lang_id
                WHERE ll.lang_id = ? AND lc.constant_name = ?";
    $res = sqlStatementNoLog($sql, array($lang_id, $constant));
    $row = sqlFetchArray($res);

    if (!empty($row['definition'])) {
        return $row['definition'];
    } else {
        return $constant;
    }
}

/*
 * Function:    cron_GetNotificationSettings
 * Purpose:     get notification settings
 */
function cron_GetNotificationSettings()
{
    $strQuery = "SELECT * FROM notification_settings WHERE type='SMS/Email Settings'";
    $vectNotificationSettings = sqlFetchArray(sqlStatement($strQuery));

    return( $vectNotificationSettings );
}
