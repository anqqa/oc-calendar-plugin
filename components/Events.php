<?php namespace Klubitus\Calendar\Components;

use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Cms\Classes\Page;
use Klubitus\Calendar\Models\Event as EventModel;
use Lang;
use October\Rain\Support\Collection;


class Events extends ComponentBase {

    const PERIOD_DAY = 'day';
    const PERIOD_WEEK = 'week';
    const PERIOD_MONTH = 'month';

    /**
     * @var  Carbon  Active date
     */
    public $date;

    /**
     * @var  string
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
            'day' => [
                'title'             => 'Event List Day',
                'placeholder'       => 'Optional',
                'default'           => '{{ :day }}',
                'type'              => 'string',
                'validationPattern' => '^[0-3]?[0-9]$',
            ],
            'week' => [
                'title'             => 'Event List Week',
                'placeholder'       => 'Optional',
                'default'           => '{{ :week }}',
                'type'              => 'string',
                'validationPattern' => '^[0-5]?[0-9]$',
            ],
            'month' => [
                'title'             => 'Event List Month',
                'placeholder'       => 'Optional',
                'default'           => '{{ :month }}',
                'type'              => 'string',
                'validationPattern' => '^[0-1]?[0-9]$',
            ],
            'year' => [
                'title'             => 'Event List Year',
                'placeholder'       => 'Optional',
                'default'           => '{{ :year }}',
                'type'              => 'string',
                'validationPattern' => '^[0-3][0-9]{3}$',
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
        switch ($this->datePeriod) {
            case self::PERIOD_WEEK:
                $events = EventModel::with('flyers.image')
                    ->week($this->date)
                    ->get();
                break;

            default:
                return [];
        }

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

        // @TODO: Move somewhere else! Now repeating here and there..
        // Ensure current locale for localized titles
        $locale = Lang::getLocale();
        setlocale(LC_TIME, sprintf('%s_%s.UTF-8', $locale, strtoupper($locale)));

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
            $date = Carbon::create($year, $month, $day ?: 1);
        }

        if ($day) {
            $this->datePeriod = self::PERIOD_DAY;

            $previousDate = $date->copy()->subDay();
            $previousText = Lang::get('klubitus.calendar::lang.pagination.previous_day');
            $previousUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $previousDate->year,
                'month' => $previousDate->month,
                'day'   => $previousDate->day,
            ]);
            $nextDate = $date->copy()->addDay();
            $nextText = Lang::get('klubitus.calendar::lang.pagination.next_day');
            $nextUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $nextDate->year,
                'month' => $nextDate->month,
                'day'   => $nextDate->day,
            ]);

            $title = ucfirst($date->formatLocalized('%A, %e. %B %Y'));
        } else if ($month) {
            $this->datePeriod = self::PERIOD_MONTH;

            $previousDate = $date->copy()->subMonth();
            $previousText = Lang::get('klubitus.calendar::lang.pagination.previous_month');
            $previousUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $previousDate->year,
                'month' => $previousDate->month,
            ]);
            $nextDate = $date->copy()->addDay();
            $nextText = Lang::get('klubitus.calendar::lang.pagination.next_month');
            $nextUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year'  => $nextDate->year,
                'month' => $nextDate->month,
            ]);

            $title = ucfirst($date->formatLocalized('%B %Y'));
        } else if ($week) {
            $this->datePeriod = self::PERIOD_WEEK;

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
            $previousText = Lang::get('klubitus.calendar::lang.pagination.previous_week');
            $previousUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year' => $previousDate->year + (int)($previousDate->year < $date->year && $previousDate->weekOfYear < $date->weekOfYear),
                'week' => $previousDate->weekOfYear,
            ]);
            $nextDate = $date->copy()->addWeek();
            $nextText = Lang::get('klubitus.calendar::lang.pagination.next_week');
            $nextUrl  = $this->controller->pageUrl($this->paginationPage, [
                'year' => $nextDate->year + (int)($nextDate->year == $date->year && $nextDate->weekOfYear < $date->weekOfYear),
                'week' => $nextDate->weekOfYear,
            ]);

            $firstDay = $date->copy()->startOfWeek();
            $weekDate = $firstDay->format('Y-m-d');
            $thisWeek = Carbon::create()->startOfWeek();
            switch ($weekDate) {

                case $thisWeek->format('Y-m-d'):
                    $title = Lang::get('klubitus.calendar::lang.title.events_this_week');
                    break;

                case $thisWeek->addWeek()->format('Y-m-d'):
                    $title = Lang::get('klubitus.calendar::lang.title.events_next_week');
                    break;

                case $thisWeek->subWeeks(2)->format('Y-m-d'):
                    $title = Lang::get('klubitus.calendar::lang.title.events_last_week');
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

                    $title = Lang::get('klubitus.calendar::lang.title.events_week', [
                        'from' => $from,
                        'to'   => $lastDay->format('j.n.Y'),
                        'week' => ltrim($lastDay->format('W/Y'), 0)
                    ]);

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
