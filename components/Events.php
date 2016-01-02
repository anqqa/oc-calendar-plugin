<?php namespace Klubitus\Calendar\Components;

use Carbon\Carbon;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Klubitus\Calendar\Models\Event as EventModel;


class Events extends ComponentBase {

    /**
     * @var  string  Event page name reference.
     */
    public $eventPage;

    /**
     * @var  Klubitus\Calendar\Models\Event  Event collection cache.
     */
    public $events;


    public function componentDetails() {
        return [
            'name'        => 'Detailed Events',
            'description' => 'Lists detaild events.'
        ];
    }


    public function defineProperties() {
        return [
            'eventPage' => [
                'title'       => 'Event Page',
                'description' => 'Page name for a single event.',
                'type'        => 'dropdown',
            ]
        ];
    }


    public function getPropertyOptions($property) {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }


    public function listEvents() {
        if ($this->events) {
            return $this->events;
        }

        $date = Carbon::today();
        $events = EventModel::week($date);

        return $this->events = $events;
    }


    public function onRun() {
        $this->prepareVars();

        $this->page['events'] = $this->listEvents();
    }


    public function prepareVars() {
        $this->eventPage = $this->page['eventPage'] = $this->property('eventPage');
    }
}
