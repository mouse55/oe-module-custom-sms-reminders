<?php

/* interface/modules/custom_modules/oe-module-custom-sms-reminders/public/sms_textbelt.php
 * Handles communication with the textbelt gateway. Uses the php CURL module to communicate
 * with the gateway via HTTP/S. If needed, install this with "sudo apt-get isntall php-curl".
 *
 * For more information about textbelt services visit https://www.textbelt.com
 * DRH adapted 1/17/22 from openemr/modules/sms_email_reminder/sms_clickatell.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    David Hantke
 * @copyright Copyright (c) 2022 David Hantke
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

class sms
{
    /**
    * Class constructor
    * Create SMS object and authenticate SMS gateway
    * @return object New SMS object.
    * @access public
    */
    function __construct()
    {
        $this->base_s = "https://textbelt.com/text";
    }

    /**
    * Send SMS message
    * @param to mixed  The destination address.
    * @param from mixed  The source/sender address
    * @param text mixed  The text content of the message
    * @return mixed  "OK" or script die
    * @access public
    */
    function send($to = null, $text = null)
    {
		global $SMS_GATEWAY_APIKEY;//, $TEXTTO; // DRH additions (not certain if $TEXTTO is needed)

        /* Check SMS $text length */
		if (strlen($text) > 459) {
			die("Your message is too long! (Current length=" . strlen($text) . ")");
		}

		/* Does message need to be concatenated? */
		if (strlen($text) > 160) {
			$concat = "&concat=3";
		} else {
			$concat = "";
		}

        /* Check $to is not empty */
        if (empty($to)) {
            die("You didn't specify destination address (TO)!");
        }

        /* Reformat $to number = 10 digits, no spaces or other symbols */
        $cleanup_chr = array ("+", " ", "(", ")", "-", "\r", "\n", "\r\n"); // DRH added "-"
        $to = str_replace($cleanup_chr, "", $to);
        //$TEXTTO = $to; // not certain if needed

        /* Send SMS now */
        $this->_chk_curl();
		$ch = curl_init($this->base_s);
		$data = array(
		  'phone' => $to,
		  'message' => $text,
		  'key' => $SMS_GATEWAY_APIKEY,
		);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		//echo $response; // DRH un-remark for testing
		return $response;
    }

    /**
    * Check for CURL PHP module
    * @access private
    */
    function _chk_curl()
    {
        if (!extension_loaded('curl')) {
            die("This SMS API class can not work without CURL PHP module! Try using fopen sending method.");
        }
    }
}
