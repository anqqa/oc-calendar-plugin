<?php namespace Klubitus\Calendar\Components;

use Carbon\Carbon;
use Cms\Classes\Page;
use Cms\Classes\ComponentBase;
use Klubitus\Calendar\Models\Event as EventModel;


class Events extends ComponentBase {

    /**
     * @var  Carbon  Active date
     */
    public $date;

    /**
     * @var  string  Active date period: day, week, month
     */
    public $datePeriod;

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
            ],
            'datePeriod' => [
                'title'       => 'Event List Period',
                'description' => 'Time period to list evens for given date.',
                'default'     => 'week',
                'type'        => 'dropdown',
                'options'     => [ 'day', 'week', 'month' ],
//                'group'       => 'Date',
            ],
            'day' => [
                'title'             => 'Event List Day',
                'placeholder'       => 'Optional',
                'default'           => '{{ :day }}',
                'type'              => 'string',
                'validationPattern' => '^[0-3]?[0-9]$',
//                'group'             => 'Date',
            ],
            'week' => [
                'title'             => 'Event List Week',
                'placeholder'       => 'Optional',
                'default'           => '{{ :week }}',
                'type'              => 'string',
                'validationPattern' => '^[0-5]?[0-9]$',
//                'group'             => 'Date',
            ],
            'month' => [
                'title'             => 'Event List Month',
                'placeholder'       => 'Optional',
                'default'           => '{{ :month }}',
                'type'              => 'string',
                'validationPattern' => '^[0-1]?[0-9]$',
//                'group'             => 'Date',
            ],
            'year' => [
                'title'             => 'Event List Year',
                'placeholder'       => 'Optional',
                'default'           => '{{ :year }}',
                'type'              => 'string',
                'validationPattern' => '^[0-3][0-9]{3}$',
//                'group'             => 'Date',
            ],
        ];
    }


    public function getEventPageOptions() {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }


    public function listEvents() {
        if ($this->events) {
            return $this->events;
        }

        $events = EventModel::week($this->date);

        return $this->events = $events;
    }


    public function onRun() {
        $this->prepareVars();

        $this->page['events'] = $this->listEvents();
    }


    public function prepareVars() {
        $year  = $this->property('year');
        $month = $this->property('month');
        $day   = $this->property('day');
        $week  = $this->property('week');
        $date  = Carbon::create($year, $month, $day);

        if ($day) {
            $range = 'day';
        } else if ($month) {
            $range = 'month';
        } else if ($week) {
            $range = 'week';
            $date  = $date->startOfYear()->addWeeks($week - 1);
        } else if ($year) {
            $range = 'year';
        } else {
            $range = 'week';
        }

        $this->date = $date;
        $this->eventPage = $this->page['eventPage'] = $this->property('eventPage');
    }
}
