<?php namespace Klubitus\Calendar\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Klubitus\Calendar\Classes\FacebookImporter;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use RainLab\User\Models\User as UserModel;
use System\Classes\SettingsManager;


/**
 * Facebook Import Back-end Controller
 */
class FacebookImport extends Controller {

    /**
     * @var  bool
     */
    public $importEnabled;

    /**
     * @var  string
     */
    public $importUrl;

    /**
     * @var  UserModel
     */
    public $importUser;


    public function __construct() {
        parent::__construct();

        BackendMenu::setContext('Klubitus.Calendar', 'calendar', 'facebookimport');
        SettingsManager::setContext('Klubitus.Calendar', 'settings');

        $this->vars['importEnabled'] = $this->importEnabled = (bool)CalendarSettings::get('facebook_import_enabled');
        $this->vars['importUrl'] = $this->importUrl = CalendarSettings::get('facebook_import_url');
        $this->vars['importUser'] = $this->importUser = UserModel::find(CalendarSettings::get('facebook_import_user_id'));
    }


    public function import() {
        $this->index_onFacebookImport(false);
    }


    public function index() {}


    public function index_onFacebookImport($save = true) {
        if (!$this->importUrl || !$this->importUser) {
            Flash::error('Facebook import URL and user are required.');

            return;
        }

        $importer = new FacebookImporter($this->importUser->id, $this->importUrl);

        $result = $importer->import($save);

        $this->vars['added']    = $result['added'];
        $this->vars['updated']  = $result['updated'];
        $this->vars['skipped']  = $result['skipped'];
        $this->vars['imported'] = $result['imported'];

        foreach ($result['errors'] as $error) {
            Flash::error($error);
        }
    }


    public function index_onFacebookImportTest() {
        $this->index_onFacebookImport(false);
    }

}
