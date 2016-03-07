<?php namespace Klubitus\Calendar\Models;

use Carbon\Carbon;
use Cms\Classes\Controller;
use Db;
use Klubitus\Calendar\Models\Event as EventModel;
use Model;
use October\Rain\Database\QueryBuilder;
use Str;


/**
 * Flyer Model
 */
class Flyer extends Model {

    /**
     * @var  string  The database table used by the model.
     */
    public $table = 'flyers';

    /**
     * @var  array  Guarded fields
     */
    protected $guarded = [];

    /**
     * @var  array  Fillable fields
     */
    protected $fillable = ['image', 'event', 'author', 'author_id', 'begins_at', 'name'];

    protected $dates = ['begins_at'];

    /**
     * @var  array  Relations
     */
    public $belongsTo = [
        'event'  => 'Klubitus\Calendar\Models\Event',
        'author' => 'RainLab\User\Models\User',

        'legacyImage' => ['Klubitus\Gallery\Models\LegacyImage', 'key' => 'image_id'],
    ];
    public $morphMany = [
        'comments' => ['Klubitus\Comment\Models\Comment', 'name' => 'commentable'],
    ];
    public $attachOne = [
        'image' => 'Klubitus\Gallery\Models\File'
    ];


    public static function countsPerMonth() {
        $months = Db::table('flyers')
            ->select(
                Db::raw("
(CASE
    WHEN begins_at IS NULL THEN '0000 00'
    WHEN TO_CHAR(begins_at, 'DDD HH24 MI') = '001 00 00' THEN TO_CHAR(begins_at, 'YYYY 00')
    ELSE TO_CHAR(begins_at, 'YYYY MM')
END) AS month
"),
                Db::raw('COUNT(1) AS month_count')
            )
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->lists('month_count', 'month');

        $counts = [];
        foreach ($months as $date => $count) {
            list($year, $month) = explode(' ', $date);
            $year  = (int)$year;
            $month = (int)$month;
            $count = (int)$count;

            if (!isset($counts[$year])) {
                $counts[$year] = [$month => $count];
            }
            else {
                $counts[$year][$month] = $count;
            }
        }

        return $counts;
    }


    /**
     * Import flyer from url to event.
     *
     * @param  EventModel  $event
     * @param  string      $url
     * @param  Flyer       $flyer  Replaced flyer
     * @return  static
     */
    public static function importToEvent(EventModel $event, $url, Flyer $flyer = null) {
        $flyer = $flyer ?: new static;
        $flyer->author_id = $event->author_id;
        $flyer->event_id  = $event->id;
        $flyer->name      = $event->name;
        $flyer->begins_at = $event->begins_at;

        Db::transaction(function() use ($flyer, $url) {
            $flyer->save();
            $flyer->image()->create(['data' => $url]);
        });

        return $flyer;
    }


    /**
     * Get models by date.
     *
     * @param   QueryBuilder  $query
     * @param   int           $year
     * @param   int           $month
     * @param   int           $day
     * @return  QueryBuilder
     */
    public function scopeDate($query, $year, $month = null, $day = null) {
        if (!$year) {

            // Unknown year
            $query->whereNull('begins_at');

        }
        else if ($month === 0 || $month === '0') {

            // Unknown month
            $query->where('begins_at', Carbon::create($year, 1, 1, 0, 0, 0));

        }
        else {
            $from = Carbon::create($year, $month ?: 1, $day ?: 1)->startOfDay();

            if ($day) {
                $to = $from->copy()->addDay();
            }
            else if ($month) {
                $to = $from->copy()->addMonth();
            }
            else {
                $to = $from->copy()->addYear();
            }

            $query
                ->whereBetween('begins_at', [$from, $to->subSecond()])
                ->orderBy(Db::raw("DATE_TRUNC('day', begins_at)"), 'ASC', 'ASC');
        }

        return $query->orderBy('name', 'ASC');
    }


    /**
     * Get latest models.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopeRecentFlyers($query) {
        return $query->orderBy('created_at', 'desc');
    }


    /**
     * Search models
     *
     * @param   QueryBuilder  $query
     * @param   string        $search
     * @return  QueryBuilder
     */
    public function scopeSearch($query, $search) {
        $search = trim($search);

        if (strlen($search)) {

            // Parse search
            $name = [];
            $venue = [];
            $city = [];

            $words = explode(' ', mb_strtolower($search));
            foreach ($words as $word) {
                $tokens = explode(':', $word, 2);

                // Defaults to event name
                if (count($tokens) == 1) {
                    $name[] = $word;

                    continue;
                }

                switch ($tokens[0]) {
                    case 'city':
                    case 'in':
                        $city[] = $tokens[1];
                        break;

                    case 'venue':
                    case 'at':
                        $venue[] = $tokens[1];
                        break;

                    default:
                        $name[] = $word;
                }
            }

            $query->where(function($query) use ($name, $venue, $city) {
                if ($name) {
                    $query->searchWhere(implode(' ', $name), 'name');
                }

                $query->whereHas('event', function($query) use ($name, $venue, $city) {
                    if ($name) {
                        $query->searchWhere(implode(' ', $name), 'name');
                    }

                    if ($venue) {
                        $query->searchWhere(implode(' ', $venue), 'venue_name');
                    }

                    if ($city) {
                        $query->searchWhere(implode(' ', $city), 'city_name');
                    }
                });
            });
        }

        return $query;
    }


    /**
     * Set current object url.
     *
     * @param  string      $pageName
     * @param  Controller  $controller
     * @return  string
     */
    public function setUrl($pageName, Controller $controller) {
        $params = [
            'flyer_id' => $this->id . '-' . Str::slug($this->name)
        ];

        return $this->url = $controller->pageUrl($pageName, $params, false);
    }

}
