<?php namespace Klubitus\Calendar\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Carbon\Carbon;
use Db;
use Flash;
use Klubitus\Calendar\Classes\VCalendar;
use Klubitus\Calendar\Models\Event as EventModel;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use Klubitus\Facebook\Classes\GraphAPI;
use October\Rain\Exception\SystemException;
use October\Rain\Support\Arr;
use October\Rain\Support\Collection;
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
        $this->index_onFacebookImport();
    }


    public function index() {

    }


    public function index_onFacebookImport($save = true) {
        if (!$this->importUrl || !$this->importUser) {
            Flash::error('Facebook import URL and user are required.');

            return;
        }

        $this->vars['added']   = $added   = [];
        $this->vars['updated'] = $updated = [];
        $this->vars['skipped'] = $skipped = [];

        try {
            $vevents = VCalendar::getFromUrl($this->importUrl, true);
            if (!$vevents) {
                Flash::error('No events found.');

                return;
            }

            $this->vars['imported'] = $events = Collection::make(VCalendar::parseEvents($vevents))->keyBy('facebook_id');

            // We are interested only in upcoming events
            $upcoming = $events->filter(function(EventModel $event) {
                return $event->ends_at > Carbon::create();
            });

            // Get existing Facebook events
            /** @var  Collection  $existing */
            $existing = EventModel::upcoming()
                ->where(function($query) use ($upcoming) {
                    $query->facebook($upcoming->keys()->toArray())
                        ->orWhere(DB::raw('LOWER(url)'), 'LIKE', '%facebook.com/events%');
                })
                ->get();

            /** @var  EventModel  $upcomingEvent */
            foreach ($upcoming as $upcomingEvent) {
                $existingEvent = $existing->first(function($key, EventModel $event) use ($upcomingEvent) {
                    return $event->facebook_id == $upcomingEvent->facebook_id
                        || ($event->url && strpos($event->url, $upcomingEvent->facebook_id));
                });

                if ($existingEvent) {
                    if ($upcomingEvent->updated_at > $existingEvent->updated_at) {
                        $existingEvent->fill([
                            'name' => $upcomingEvent->name,
                            'url' => $upcomingEvent->url,
                            'begins_at' => $upcomingEvent->begins_at,
                            'ends_at' => $upcomingEvent->ends_at,
                            'facebook_id' => $upcomingEvent->facebook_id,
                            'facebook_organizer' => $upcomingEvent->facebook_organizer,
                        ]);
                        $this->updateEvent($existingEvent);

                        $updated[$existingEvent->facebook_id] = $existingEvent;

                        if ($save) {
                            $existingEvent->save();

                            // @TODO: Move to newsfeed plugin
                            $this->createNewsfeedItem($existingEvent->id, false);
                        }
                    }
                    else {
                        $skipped[$existingEvent->facebook_id] = $existingEvent;
                    }
                }
                else {
                    $this->updateEvent($upcomingEvent);

                    $added[$upcomingEvent->facebook_id] = $upcomingEvent;

                    if ($save) {
                        $upcomingEvent->save();

                        // @TODO: Move to newsfeed plugin
                        $this->createNewsfeedItem($upcomingEvent->id, true);
                    }
                }
            }

            $this->vars['added']   = $added;
            $this->vars['updated'] = $updated;
            $this->vars['skipped'] = $skipped;

        }
        catch (SystemException $e) {
            Flash::error($e->getMessage());
        }
    }


    public function index_onFacebookImportTest() {
        return $this->index_onFacebookImport(false);
    }

    protected function createNewsfeedItem($eventId, $newItem = true) {
        Db::table('newsfeeditems')->insert([
            'user_id'   => $this->importUser->id,
            'stamp'     => time(),
            'class'     => 'events',
            'type'      => $newItem ? 'event' : 'event_edit',
            'data'      => json_encode([ 'event_id' => $eventId ]),
            'target_id' => $eventId
        ]);
    }


    /**
     * Update Event with missing data using GraphAPI.
     *
     * @param   EventModel  $event
     * @throws  SystemException
     */
    protected function updateEvent(EventModel $event) {
        $accessToken = GraphAPI::instance()->appAccessToken();
        try {
            $response = GraphAPI::instance()->get('/' . $event->facebook_id, [ 'cover', 'description', 'place', 'ticket_uri' ], $accessToken);
        }
        catch (SystemException $e) {
            Flash::error($e->getMessage());

            return;
        }

        $eventObject = $response->getGraphEvent();
        $coverObject = $eventObject->getCover();

        $event->info = $eventObject->getDescription();
        $event->ticket_url = $eventObject->getTicketUri();
        $event->flyer_url = $event->flyer_front_url = $coverObject->getSource();

        $placeObject = $eventObject->getPlace();
        if ($placeObject) {
            $event->venue_name = $placeObject->getName();

            $locationObject = $placeObject->getLocation();
            if ($locationObject) {
                $event->city_name = $locationObject->getCity();
            }
        }
    }

}
