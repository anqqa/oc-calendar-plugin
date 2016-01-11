<?php namespace Klubitus\Calendar\Components;

use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Cms\Classes\Page;
use Klubitus\Calendar\Models\Event as EventModel;
use October\Rain\Support\Collection;


class Events extends ComponentBase {

    const PERIOD_DAY   = 'day';
    const PERIOD_WEEK  = 'week';
    const PERIOD_MONTH = 'month';


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

    /**
     * @var  string
     */
    public $pageTitle;

    /**
     * @var  string  Paginated events page name reference.
     */
    public $paginationPage;


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
            'paginationPage' => [
                'title'       => 'Pagination Page',
                'description' => 'Page name for paginated events.',
                'type'        => 'dropdown',
            ],
            'datePeriod' => [
                'title'       => 'Event List Period',
                'description' => 'Time period to list evens for given date.',
                'default'     => 'week',
                'type'        => 'dropdown',
                'options'     => [
                    self::PERIOD_DAY   => 'day',
                    self::PERIOD_WEEK  => 'week',
                    self::PERIOD_MONTH => 'month'
                ],
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


    public function getPropertyOptions($property) {
        if ($property == 'eventPage' || $property == 'paginationPage') {
            return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
        }

        return self::getPropertyOptions($property);
    }


    public function listEvents() {
        if ($this->events) {
            return $this->events;
        }

        /** @var  Collection  $events */
        $events = EventModel::week($this->date)->get();

        // Add date and url
        $events->each(function(EventModel $event) {
            $event->date = $event->begins_at->toDateString();
            $event->setUrl($this->eventPage, $this->controller);
        });

        return $this->events = $events;
    }


    public function onRun() {
        $this->prepareVars();

        $this->page['events'] = $this->listEvents();
    }


    public function prepareVars() {
        $this->eventPage      = $this->page['eventPage']      = $this->property('eventPage');
        $this->paginationPage = $this->page['paginationPage'] = $this->property('paginationPage');

        // Parse date period, default to current week
        $year  = $this->property('year');
        $month = $this->property('month');
        $day   = $this->property('day');
        $week  = $this->property('week');

        if (!$year) {
            $date = Carbon::create()->startOfWeek();
            $week = $date->weekOfYear;
        }
        else {
            $date = Carbon::create($year, $month, $day);
        }

        if ($day) {
            $previousDate = $date->copy()->subDay();
            $previousText = 'Previous day';
            $previousUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $previousDate->year,
                'month' => $previousDate->month,
                'day'   => $previousDate->day,
            ]);
            $nextDate = $date->copy()->addDay();
            $nextText = 'Next day';
            $nextUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $nextDate->year,
                'month' => $nextDate->month,
                'day'   => $nextDate->day,
            ]);

            $title = 'Events ' . $date->format("l, F j Y");
        } else if ($month) {
            $previousDate = $date->copy()->subMonth();
            $previousText = 'Previous month';
            $previousUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $previousDate->year,
                'month' => $previousDate->month,
            ]);
            $nextDate = $date->copy()->addDay();
            $nextText = 'Next month';
            $nextUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $nextDate->year,
                'month' => $nextDate->month,
            ]);

            $title = 'Events ' . $date->format("F Y");
        } else if ($week) {
            $date->startOfYear();

            // Is the first day of the year in the last week of last year?
            if ($date->weekOfYear != 1) {
                $date->addWeek();
            }

            if ($week > 1) {
                $date->addWeeks($week - 1);
            }

            $date->startOfWeek();

            $previousDate = $date->copy()->subWeek();
            $previousText = 'Previous week';
            $previousUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year' => $previousDate->year,
                'week' => $previousDate->weekOfYear,
            ]);
            $nextDate = $date->copy()->addWeek();
            $nextText = 'Next week';
            $nextUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year' => $nextDate->year,
                'week' => $nextDate->weekOfYear,
            ]);

            $firstDay = $date->copy()->startOfWeek();
            $weekDate = $firstDay->format('Y-m-d');
            $thisWeek = Carbon::create()->startOfWeek();
            switch ($weekDate) {

                case $thisWeek->format('Y-m-d'):
                    $title = 'Events this week';
                    break;

                case $thisWeek->addWeek()->format('Y-m-d'):
                    $title = 'Events next week';
                    break;

                case $thisWeek->subWeeks(2)->format('Y-m-d'):
                    $title = 'Events last week';
                    break;

                default:
                    $lastDay  = $firstDay->copy()->addDays(6);
                    if ($firstDay->year != $lastDay->year) {
                        $from = $firstDay->format('j.n.Y');
                    }
                    else if ($firstDay->month != $lastDay->month) {
                        $from = $firstDay->format('j.n.');
                    }
                    else {
                        $from = $firstDay->format('j.');
                    }

                    $title = 'Events ' . $from . ' - ' . $lastDay->format('j.n.Y');

            }

        } else {
            return;
        }

        $this->pageTitle = $this->page['title'] = $title;
        $this->date = $this->page['date'] = $date;
        $this->page['pagination'] = compact(
            'previousDate', 'previousText', 'previousUrl',
            'nextDate', 'nextText', 'nextUrl'
        );
    }
}
