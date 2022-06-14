<?php

/* interface/modules/custom_modules/oe-module-custom-sms-reminders/src/Bootstrap.php
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    David Hantke
 * @copyright Copyright (c) 2022 David Hantke
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\CustomModuleSMSReminders;

/**
 * Note the below use statements are importing classes from the OpenEMR core codebase
 */
use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Core\Kernel;
use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Services\Globals\GlobalSetting;
use OpenEMR\Menu\MenuEvent;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

// we import our own classes here.. although this use statement is unnecessary it forces the autoloader to be tested.
// use OpenEMR\Modules\CustomModuleSMSReminders\CustomSMSRemindersRestController;

class Bootstrap
{
    const MODULE_INSTALLATION_PATH = "/interface/modules/custom_modules/";
    const MODULE_NAME = "oe-module-custom-sms-reminders";

    /**
     * @var EventDispatcherInterface The object responsible for sending and subscribing to events through the OpenEMR system
     */
    private $eventDispatcher;

    /**
     * @var GlobalConfig Holds our module global configuration values that can be used throughout the module.
     */
    private $globalsConfig;

    /**
     * @var string The folder name of the module.  Set dynamically from searching the filesystem.
     */
    private $moduleDirectoryName;

    /**
     * @var SystemLogger
     */
    private $logger;

    public function __construct(EventDispatcherInterface $eventDispatcher, ?Kernel $kernel = null)
    {
        global $GLOBALS;

        if (empty($kernel)) {
            $kernel = new Kernel();
        }

        $this->moduleDirectoryName = basename(dirname(__DIR__));
        $this->eventDispatcher = $eventDispatcher;

        // we inject our globals value.
        $this->globalsConfig = new GlobalConfig($GLOBALS);
        $this->logger = new SystemLogger();
    }

    public function subscribeToEvents()
    {
        $this->addGlobalSettings();

        // we only add the rest of our event listeners and configuration if we have been fully setup and configured
        if ($this->globalsConfig->isConfigured()) {
            $this->registerMenuItems();
        }
    }

    /**
     * @return GlobalConfig
     */
    public function getGlobalConfig()
    {
        return $this->globalsConfig;
    }

    public function addGlobalSettings()
    {
        $this->eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, [$this, 'addGlobalSettingsSection']);
    }

    public function addGlobalSettingsSection(GlobalsInitializedEvent $event)
    {
        global $GLOBALS;

        $service = $event->getGlobalsService();
        $section = xlt("SMS Reminders");
        $service->createSection($section, 'Portal');

        $settings = $this->globalsConfig->getGlobalSettingSectionConfiguration();
        $handle = fopen('/Users/david/Sites/openemr/interface/modules/custom_modules/oe-module-custom-sms-reminders/public/settings.txt', 'w') or die("Couldn't do it!");

        foreach ($settings as $key => $config) {
            $value = $GLOBALS[$key] ?? $config['default'];
            $service->appendToSection(
                $section,
                $key,
                new GlobalSetting(
                    xlt($config['title']),
                    $config['type'],
                    $value,
                    xlt($config['description']),
                    true
                )
            );

            $sms_settings .= '"' . $value . '",';
        }

        //$sms_settings = substr_replace($sms_settings, "", -1);
        fwrite($handle, $sms_settings);
        fwrite($handle, '"' . substr($GLOBALS['fileroot'], 0, -8));
        fwrite($handle, '"');
        fclose($handle);
    }

    public function registerMenuItems()
    {
        // if ($this->getGlobalConfig()->getGlobalSetting(GlobalConfig::CONFIG_ENABLE_MENU)) { // DRH
            /**
             * @var EventDispatcherInterface $eventDispatcher
             * @var array $module
             * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
             * @global                       $module @see ModulesApplication::loadCustomModule
             */
            $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, [$this, 'addCustomModuleMenuItem']);
        // } // DRH
    }

    public function addCustomModuleMenuItem(MenuEvent $event)
    {
        $menu = $event->getMenu();

        $menuItem = new \stdClass();
        $menuItem->requirement = 0;
        $menuItem->target = 'adm';
        $menuItem->menu_id = 'adm0';
        $menuItem->label = xlt("Custom SMS Reminders");
        // TODO: pull the install location into a constant into the codebase so if OpenEMR changes this location it
        // doesn't break any modules.
        $menuItem->url = "/interface/modules/custom_modules/oe-module-custom-sms-reminders/public/add_edit_message.php";
        $menuItem->children = [];

        /**
         * This defines the Access Control List properties that are required to use this module.
         * Several examples are provided
         */
        $menuItem->acl_req = [];

        /**
         * If you would like to restrict this menu to only logged in users who have access to see all user data
         */
        //$menuItem->acl_req = ["admin", "users"];

        /**
         * If you would like to restrict this menu to logged in users who can access patient demographic information
         */
        //$menuItem->acl_req = ["users", "demo"];


        /**
         * This menu flag takes a boolean property defined in the $GLOBALS array that OpenEMR populates.
         * It allows a menu item to display if the property is true, and be hidden if the property is false
         */
        //$menuItem->global_req = ["custom_skeleton_module_enable"];

        /**
         * If you want your menu item to allows be shown then leave this property blank.
         */
        $menuItem->global_req = [];

        foreach ($menu as $item) {
            if ($item->menu_id == 'admimg') {
                $item->children[] = $menuItem;
                break;
            }
        }

        $event->setMenu($menu);

        return $event;
    }

    private function getPublicPath()
    {
        return self::MODULE_INSTALLATION_PATH . ($this->moduleDirectoryName ?? '') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
    }
}
