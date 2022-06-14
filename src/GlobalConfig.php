<?php

/* interface/modules/custom-modules/oe-module-custm-sms-reminders/src/GlobalConfig.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    David Hantke
 * @copyright Copyright (c) 2022 David Hantke
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
 
namespace OpenEMR\Modules\CustomModuleSMSReminders;

use OpenEMR\Services\Globals\GlobalSetting;
// use OpenEMR\Events\Globals\GlobalsInitializedEvent; // DRH addition

class GlobalConfig
{
    const CONFIG_ENABLE_REMINDERS = "oe_enable_reminders";
    const CONFIG_SMS_GATEWAY_APIKEY = "oe_sms_gateway_apikey";
    const CONFIG_SMS_SEND_BEFORE_HOURS = 'oe_sms_send_before_hours';
    const CONFIG_OEMR_INSTALL_DIRECTORY = 'oe_oemr_install_directory';
    const CONFIG_ENABLE_MENU = 'oe_sms_reminders_add_menu_button';

    private $globalsArray;

    public function __construct(array &$globalsArray)
    {
        $this->globalsArray = $globalsArray;
    }

    /**
     * Returns true if all of the settings have been configured.  Otherwise it returns false.
     * @return bool
     */
    public function isConfigured()
    {
        $keys = [self::CONFIG_SMS_GATEWAY_APIKEY, self::CONFIG_SMS_SEND_BEFORE_HOURS];
        foreach ($keys as $key) {
            $value = $this->getGlobalSetting($key);
            if (empty($value)) {
                return false;
            }
        }
        return true;
    }

    public function getSmsGatewayApikeyOption()
    {
        return $this->getGlobalSetting(self::CONFIG_SMS_GATEWAY_APIKEY);
    }

    public function getSmsSendBeforeHours()
    {
        return $this->getGlobalSetting(self::CONFIG_SMS_SEND_BEFORE_HOURS);
    }

    public function getGlobalSetting($settingKey)
    {
        return $this->globalsArray[$settingKey] ?? null;
    }

    public function getGlobalSettingSectionConfiguration()
    {
        $settings = [
            self::CONFIG_ENABLE_REMINDERS => [
                'title' => 'Enable SMS Service Reminders',
                'description' => 'Disabling temporarily deactivates text reminders. To deactivate permanently simply uninstall the module.',
                'type' => GlobalSetting::DATA_TYPE_BOOL,
                'default' => '1'
            ],
            self::CONFIG_SMS_GATEWAY_APIKEY => [
                'title' => 'SMS Gateway Key',
                'description' => 'Obtain from www.textbelt.com',
                'type' => GlobalSetting::DATA_TYPE_TEXT,
                'default' => 'textbelt'
            ],
            self::CONFIG_SMS_SEND_BEFORE_HOURS => [
                'title' => 'SMS Send Before Hours',
                'description' => 'Maximum lead time.',
                'type' => GlobalSetting::DATA_TYPE_TEXT,
                'default' => '48'
            ]
        ];
        return $settings;
    }
}
