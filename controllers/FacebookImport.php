<?php namespace Klubitus\Calendar\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Klubitus\Calendar\Classes\VCalendar;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use October\Rain\Exception\SystemException;
use System\Classes\SettingsManager;


/**
 * Facebook Import Back-end Controller
 */
class FacebookImport extends Controller {
    public $importEnabled;
    public $importUrl;

    public function __construct() {
        parent::__construct();

        BackendMenu::setContext('Klubitus.Calendar', 'calendar', 'facebookimport');
        SettingsManager::setContext('Klubitus.Calendar', 'settings');

        $this->vars['importEnabled'] = $this->importEnabled = (bool)CalendarSettings::get('facebook_import_enabled');
        $this->vars['importUrl'] = $this->importUrl = CalendarSettings::get('facebook_import_url');
    }


    public function import() {
        $this->index_onFacebookImport();
    }


    public function index() {

    }


    public function index_onFacebookImport() {
        if (!$this->importUrl) {
            Flash::error('Facebook import URL is required.');

            return;
        }

        try {
            $this->vars['events'] = $events = VCalendar::parseEventsFrom($this->importUrl);
        }
        catch (SystemException $e) {
            Flash::error($e->getMessage());
        }
    }
}
