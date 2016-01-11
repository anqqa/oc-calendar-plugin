<?php namespace Klubitus\Calendar\Components;

use Cms\Classes\ComponentBase;
use Cms\Classes\Page;
use Klubitus\Calendar\Models\Event as EventModel;
use October\Rain\Support\Collection;


class EventList extends ComponentBase {

    const NEW_EVENTS     = 'new';
    const POPULAR_EVENTS = 'popular';
    const UPDATED_EVENTS = 'updated';


    /**
     * @var  string  Event page name reference.
     */
    public $eventPage;

    /**
     * @var  Klubitus\Calendar\Models\Event  Event collection cache.
     */
    public $events;

    /**
     * @var  string
     */
    public $title;


    public function componentDetails() {
        return [
            'name'        => 'Event List',
            'description' => 'Simple event list.'
        ];
    }


    public function defineProperties() {
        return [
            'eventPage' => [
                'title'       => 'Event Page',
                'description' => 'Page name for a single event.',
                'type'        => 'dropdown',
            ],
            'listTitle' => [
                'title'       => 'List Title',
                'description' => 'Title for component.',
                'type'        => 'string',
            ],
            'listType' => [
                'title'       => 'Event List Type',
                'description' => 'Type of events to list.',
                'type'        => 'dropdown',
                'default'     => self::NEW_EVENTS,
                'options'     => [
                    self::NEW_EVENTS     => 'New',
                    self::POPULAR_EVENTS => 'Popular',
                    self::UPDATED_EVENTS => 'Updated'
                ],
            ],
        ];
    }


    public function getPropertyOptions($property) {
        if ($property == 'eventPage') {
            return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
        }

        return self::getPropertyOptions($property);
    }


    public function listEvents() {

        /** @var  Collection  $events */
        switch ($this->property('listType')) {

            case self::NEW_EVENTS:
                $events = EventModel::latest()->limit(5)->get();
                break;

            case self::POPULAR_EVENTS:
                $events = EventModel::popular()->limit(5)->get();
                break;

            case self::UPDATED_EVENTS:
                $events = EventModel::recentUpdates()->limit(5)->get();
                break;

            default:
                return [];

        }

        // Add url
        $events->each(function(EventModel $event) {
            $event->setUrl($this->eventPage, $this->controller);
        });

        return $events;
    }


    public function onRun() {
        $this->eventPage = $this->property('eventPage');
        $this->events    = $this->listEvents();
        $this->title     = $this->property('listTitle');
    }

}
