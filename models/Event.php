<?php namespace Klubitus\Calendar\Models;

use ApplicationException;
use Carbon\Carbon;
use Cms\Classes\Controller;
use Illuminate\Support\Facades\DB;
use Model;
use October\Rain\Database\QueryBuilder;
use October\Rain\Support\Str;


/**
 * Event Model
 */
class Event extends Model {

    /**
     * @var  string  The database table used by the model.
     */
    public $table = 'events';

    /**
     * @var  array  Guarded fields
     */
    protected $guarded = [];

    /**
     * @var  array  Fillable fields
     */
    protected $fillable = [
        'updated_at',
        'begins_at',
        'ends_at',
        'name',
        'url',
        'ticket_url',
        'age',
        'price',
        'info',
        'music',
        'flyer_url',
        'flyer_front_url',
        'venue_name',
        'venue_url',
        'venue_hidden',
        'city_name',
        'facebook_id',
        'facebook_organizer',
    ];

    protected $dates = ['begins_at', 'ends_at'];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'favorites' => 'Klubitus\Calendar\Models\Favorite',
    ];
    public $belongsTo = [
        'author' => 'RainLab\User\Models\User',
    ];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];


    /**
     * @var  array  Validation rules
     */
    public $rules = [
        'begins_at' => 'required',
        'ends_at'   => 'required',
        'name'      => 'required',
    ];


    /**
     * Get events by date range.
     * Event date changes at 05.00 (5 am).
     *
     * @param   QueryBuilder  $query
     * @param   Carbon   $from
     * @param   Carbon   $to
     * @return  QueryBuilder
     */
    public function scopeBetween($query, Carbon $from, Carbon $to) {
        return $query
            ->where('begins_at', '<=', $to)
            ->where('ends_at', '>=', $from->copy()->addHours(5)) // Only get after 5am
            ->orderBy(DB::raw("date_trunc('day', begins_at)"), 'ASC')
            ->orderBy('city_name', 'ASC');
    }


    /**
     * Get events by Facebook event id.
     *
     * @param   QueryBuilder  $query
     * @param   array         $facebookIds
     * @return  QueryBuilder
     */
    public function scopeFacebook($query, array $facebookIds) {
        return $query->whereIn('facebook_id', $facebookIds);
    }


    /**
     * Get new events.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopeLatest($query) {
        return $query->orderBy('id', 'DESC');
    }


    /**
     * Get popular upcoming events.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopePopular($query) {
        return $query
            ->where('begins_at', '>', Carbon::create())
            ->orderBy('favorite_count', 'DESC');
    }


    /**
     * Get updated events.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopeRecentUpdates($query) {
        return $query
            ->where('update_count', '>', 0)
            ->orderBy('updated_at', 'DESC');
    }


    /**
     * Get future events.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopeUpcoming($query) {
        return $query->where('ends_at', '>', Carbon::create());
    }


    /**
     * Get events by week.
     *
     * @param   QueryBuilder  $query
     * @param   Carbon|int    $year  Year or date
     * @param   int           $week  Week number if year is int
     * @return  QueryBuilder
     *
     * @throws  ApplicationException  on missing parameters
     */
    public function scopeWeek($query, $year, $week = null) {
        if ($year instanceof Carbon) {
            $from = $year->copy()->startOfWeek();
        }
        else if ($week) {
            $from = Carbon::create($year, 1, 1)->startOfWeek();

            // Is the first day of the year on a week of last year?
            if ($from->weekOfYear != 1) {
                $from->addWeek();
            }

            if ($week > 1) {
                $from->addWeeks($week - 1);
            }
        }
        else {
            throw new ApplicationException('Week missing');
        }

        return $this->scopeBetween($query, $from, $from->copy()->endOfWeek());
    }


    /**
     * Set current object url.
     *
     * @param  string      $pageName
     * @param  Controller  $controller
     */
    public function setUrl($pageName, Controller $controller) {
        $params = [
            'event_id' => $this->id . '-' . Str::slug($this->name)
        ];

        return $this->url = $controller->pageUrl($pageName, $params);
    }

}
