<?php namespace Klubitus\Calendar\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Klubitus\Calendar\Classes\FacebookImporter;
use Klubitus\Calendar\Models\Event as EventModel;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use RainLab\User\Models\User as UserModel;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use System\Classes\SettingsManager;


/**
 * (Facebook) Import Back-end Controller
 */
class Import extends Controller {

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

        BackendMenu::setContext('Klubitus.Calendar', 'calendar', 'import');
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


    public function index_onFlyerImport($save = true) {
        $events = EventModel::whereNotNull('flyer_url')
            ->has('flyers', 0)
            ->limit(10)
            ->get();

        $cleaned  = [];
        $imported = [];
        $failed   = [];

        /** @var  EventModel  $event */
        foreach ($events as $event) {
            $event->timestamps = false;

            if (trim($event->flyer_url) == '') {
                $event->flyer_url = null;

                $cleaned[$event->id] = $event;
            }
            else {
                try {
                    $save and $event->importFlyer();

                    $imported[$event->id] = $event;
                }
                catch (FileNotFoundException $e) {
                    $failed[$event->id] = $event;
                }

            }

            $save and $event->forceSave();
        }

        $this->vars['events']   = $events;
        $this->vars['cleaned']  = $cleaned;
        $this->vars['imported'] = $imported;
        $this->vars['failed']   = $failed;
    }


    public function index_onFlyerImportTest() {
        $this->index_onFlyerImport(false);
    }

}
