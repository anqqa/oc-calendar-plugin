<?php namespace Klubitus\Calendar\Classes;

use Carbon\Carbon;
use Db;
use Facebook\GraphNodes\GraphPage;
use Klubitus\Calendar\Models\Event as EventModel;
use Klubitus\Facebook\Classes\GraphAPI;
use Klubitus\Venue\Models\Venue as VenueModel;
use October\Rain\Database\ModelException;
use October\Rain\Database\QueryBuilder;
use October\Rain\Exception\SystemException;
use October\Rain\Support\Collection;
use RainLab\User\Models\User as UserModel;


/**
 * Facebook Import Back-end Controller
 */
class FacebookImporter {

    /**
     * @var  string
     */
    public $importUrl;

    /**
     * @var  UserModel
     */
    public $importUserId;


    /**
     * FacebookImporter constructor.
     *
     * @param  int     $userId
     * @param  string  $url
     */
    public function __construct($userId, $url) {
        $this->importUserId = $userId;
        $this->importUrl    = $url;
    }


    /**
     * Create newsfeed item for create/update Event.
     *
     * @param  int   $eventId
     * @param  bool  $newItem
     *
     * @TODO: Move to newsfeed plugin!
     */
    protected function createEventNewsfeedItem($eventId, $newItem = true) {
        Db::table('newsfeeditems')->insert([
            'user_id'   => $this->importUserId,
            'stamp'     => time(),
            'class'     => 'events',
            'type'      => $newItem ? 'event' : 'event_edit',
            'data'      => json_encode([ 'event_id' => $eventId ]),
            'target_id' => $eventId
        ]);
    }


    /**
     * Create newsfeed item for create/update Venue.
     *
     * @param  int   $venueId
     * @param  bool  $newItem
     *
     * @TODO: Move to newsfeed plugin!
     */
    protected function createVenueNewsfeedItem($venueId, $newItem = true) {
        Db::table('newsfeeditems')->insert([
            'user_id'   => $this->importUserId,
            'stamp'     => time(),
            'class'     => 'venues',
            'type'      => $newItem ? 'venue' : 'venue_edit',
            'data'      => json_encode([ 'venue_id' => $venueId ]),
            'target_id' => $venueId
        ]);
    }


    /**
     * Import events from Facebook.
     *
     * @param   bool  $save
     * @return  array
     */
    public function import($save = false) {
        $added    = [];
        $errors   = [];
        $imported = [];
        $skipped  = [];
        $updated  = [];

        try {
            $vevents = VCalendar::getFromUrl($this->importUrl, true);

            if (!$vevents) {
                throw new SystemException('No events found.');
            }

            $imported = Collection::make(VCalendar::parseEvents($vevents))->keyBy('facebook_id');

            // We are interested only in upcoming events
            $upcoming = $imported->filter(function(EventModel $event) {
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
                /** @var  EventModel  $existingEvent */
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

                        $error = $this->updateEvent($existingEvent, $save);
                        if ($error) {
                            $errors[] = $error;
                        }

                        $updated[$existingEvent->facebook_id] = $existingEvent;

                        if ($save && $existingEvent->isDirty()) {
                            $existingEvent->save();

                            // @TODO: Move to newsfeed plugin
                            $this->createEventNewsfeedItem($existingEvent->id, false);
                        }
                    }
                    else {
                        $skipped[$existingEvent->facebook_id] = $existingEvent;
                    }
                }
                else {
                    $upcomingEvent->author_id = $this->importUserId;

                    $error = $this->updateEvent($upcomingEvent, $save);
                    if ($error) {
                        $errors[] = $error;
                    }

                    $added[$upcomingEvent->facebook_id] = $upcomingEvent;

                    if ($save) {
                        $upcomingEvent->save();

                        // @TODO: Move to newsfeed plugin
                        $this->createEventNewsfeedItem($upcomingEvent->id, true);
                    }
                }
            }

        }
        catch (SystemException $e) {
            $errors[] = $e->getMessage();
        }

        return compact('added', 'errors', 'imported', 'skipped', 'updated');
    }


    /**
     * Update Event with missing data using GraphAPI.
     *
     * @param   EventModel  $event
     * @param   bool        $save
     * @return  string      Error message, if any
     * @throws  SystemException
     */
    protected function updateEvent(EventModel $event, $save) {
        $accessToken = GraphAPI::instance()->getAppAccessToken();

        try {
            $response = GraphAPI::instance()->get('/' . $event->facebook_id,
                ['cover', 'description', 'place{id,name,location}', 'ticket_uri'],
                $accessToken);
        }
        catch (SystemException $e) {
            return $e->getMessage();
        }

        $eventObject = $response->getGraphEvent();
        $coverObject = $eventObject->getCover();

        $event->info = $eventObject->getDescription();
        $event->ticket_url = $eventObject->getTicketUri();

        // Don't update flyer if the current is uploaded rather than linked
        if (!$event->flyer_id) {
            $event->flyer_url = $event->flyer_front_url = $coverObject->getSource();
        }

        return $this->updateVenue($event, $eventObject->getPlace(), $save);
    }


    /**
     * Update Event with venue data and create/update Venue.
     *
     * @param   EventModel  $event
     * @param   GraphPage   $placeObject
     * @param   bool        $save
     * @return  string      Error message, if any
     */
    protected function updateVenue(EventModel $event, GraphPage $placeObject = null, $save = false) {
        static $venues = [];

        if ($placeObject) {
            $event->venue_name = $placeObject->getName();

            $locationObject = $placeObject->getLocation();
            if ($locationObject) {
                $event->city_name = $locationObject->getCity();
            }

            // Update Venue only if not already updated
            $placeId = $placeObject->getId();
            if ($placeId && !isset($venues[$placeId])) {
                /** @var  QueryBuilder  $query */
                $query = VenueModel::facebook([$placeId]);

                if ($locationObject) {
                    $query->orWhere(function($query) use ($placeObject, $locationObject) {
                        $query->where(DB::raw('lower(name)'), '=', strtolower($placeObject->getName()))
                            ->where(DB::raw('lower(city_name)'), '=', strtolower($locationObject->getCity()));
                    });
                }

                $venue = $query->first();

                if (!$venue) {
                    $newItem = true;
                    $venue = VenueModel::make([
                        'author_id' => $this->importUserId,
                        'name'      => $placeObject->getName(),
                    ]);
                }
                else {
                    $newItem = false;
                }

                $venues[$placeId] = $venue;

                if ($locationObject) {
                    $venue->fill([
                        'address'     => $locationObject->getStreet(),
                        'zip'         => $locationObject->getZip(),
                        'city_name'   => $locationObject->getCity(),
                        'country'     => $locationObject->getCountry(),
                        'latitude'    => $locationObject->getLatitude(),
                        'longitude'   => $locationObject->getLongitude(),
                        'facebook_id' => $placeId
                    ]);
                }

                if ($save) {
                    try {
                        if ($venue->isDirty()) {
                            $venue->save();

                            // @TODO: Move to newsfeed plugin
                            $this->createVenueNewsfeedItem($venue->id, $newItem);
                        }
                    } catch (ModelException $e) {
                        return $e->getMessage();
                    }
                }
            }
            elseif (isset($venues[$placeId])) {
                $venue = $venues[$placeId];
            }

            if (isset($venue)) {
                $event->venue()->associate($venue);
            }
        }

        return null;
    }

}
