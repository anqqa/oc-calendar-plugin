<?php namespace Klubitus\Calendar\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Flash;
use Klubitus\Calendar\Classes\VCalendar;
use Klubitus\Calendar\Models\Event as EventModel;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use October\Rain\Exception\SystemException;
use October\Rain\Support\Arr;
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
            $vevents = VCalendar::getFromUrl($this->importUrl, true);
            if (!$vevents) {
                Flash::error('No events found.');

                return;
            }

            $this->vars['imported'] = $events = VCalendar::parseEvents($vevents);

            // Get existing events matching new ids
            $existing = EventModel::facebook(array_keys($events))->get();
            $update = [];
            foreach ($existing as $event) {
                $update[$event->facebook_id] = $event;
            }

            $this->vars['existing'] = $existing;

            $added = $updated = $skipped = 0;
            foreach ($events as $event) {
                $existingEvent = Arr::get($update, $event->facebook_id);

                if ($existingEvent) {
                    if ($event->updated_at > $existingEvent->updated_at) {
                        $existingEvent->fill([
                            'name'       => $event->name,
                            'url'        => $event->url,
                            'begins_at'  => $event->begins_at,
                            'ends_at'    => $event->ends_at,
                            'info'       => $event->info,
                            'venue_name' => $event->venue_name,
                        ]);

                        $existingEvent->save();
                        $updated++;
                    }
                    else {
                        $skipped++;
                    }
                }
                else {
                    $event->save();

                    $added++;
                }
            }

            $this->vars['added'] = $added;
            $this->vars['updated'] = $updated;
            $this->vars['skipped'] = $skipped;

        }
        catch (SystemException $e) {
            Flash::error($e->getMessage());
        }
    }
}
