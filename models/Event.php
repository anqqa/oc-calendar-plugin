<?php namespace Klubitus\Calendar\Models;

use ApplicationException;
use Auth;
use Carbon\Carbon;
use Cms\Classes\Controller;
use Db;
use Klubitus\Calendar\Models\Flyer as FlyerModel;
use Model;
use October\Rain\Database\QueryBuilder;
use October\Rain\Database\Traits\Revisionable;
use October\Rain\Database\Traits\Validation;
use Str;
use RainLab\User\Models\User as UserModel;


/**
 * Event Model
 */
class Event extends Model {
    use Revisionable;
    use Validation;

    /**
     * @var  string  The database table used by the model.
     */
    public $table = 'events';

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

    protected $revisionable = ['name', 'info'];

    protected $dates = ['begins_at', 'ends_at'];

    /**
     * @var array Relations
     */
    public $hasMany = [
        'favorites' => 'Klubitus\Calendar\Models\Favorite',
        'flyers'    => 'Klubitus\Calendar\Models\Flyer',
    ];
    public $belongsTo = [
        'author' => 'RainLab\User\Models\User',
        'venue'  => 'Klubitus\Venue\Models\Venue',
    ];
    public $morphMany = [
        'revision_history' => ['System\Models\Revision', 'name' => 'revisionable'],
    ];

    /**
     * @var  array  Validation rules
     */
    public $rules = [
        'begins_at'       => 'required',
        'ends_at'         => 'required',
        'name'            => 'required',
        'url'             => 'url',
        'ticket_url'      => 'url',
        'flyer_url'       => 'url',
        'flyer_front_url' => 'url',
    ];


    /**
     * Import flyer from url.
     *
     * @param  string  $url
     * @param  bool    $replace
     * @return  bool
     */
    public function importFlyer($url = null, $replace = false) {
        $existingFlyer = null;

        if ($replace) {
            $existingFlyer = $this->flyers->first();

            // @TODO: Backwards compatibility, remove when Anqh is killed
            if (!$existingFlyer && $this->flyer_id) {
                $existingFlyer = FlyerModel::find($this->flyer_id);
            }
        }

        if ($url && $url != $this->flyer_url) {

            // Import flyer from new url
            $flyerUrl = $url;

        }
        else if (!$url && $this->flyer_url) {

            // Import flyer from existing url
            $flyerUrl = $this->flyer_url;

        }

        if (isset($flyerUrl)) {
            return Db::transaction(function() use ($flyerUrl, $existingFlyer) {
                $flyer = FlyerModel::importToEvent($this, $flyerUrl, $existingFlyer);

                $this->flyer_url = $flyerUrl;
                $this->flyer_front_url = url($flyer->image->getPath());

                return $this->save();
            });
        }

        return false;
    }


    /**
     * Has the user added this event to their favorites.
     *
     * @param  UserModel  $user
     * @return  bool
     */
    public function isFavorite(UserModel $user = null) {
        if (is_null($user)) {
            $user = Auth::getUser();
        }

        if (!$user) {
            return false;
        }

        return $this->favorites()->where('user_id', $user->id)->count() > 0;
    }


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
            ->where('ends_at', '>=', $from->copy()->addHours(5)); // Only get after 5am
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
     * Order events by date and city.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopeOrderDefault($query) {
        return $query
            ->orderBy(DB::raw("DATE_TRUNC('day', begins_at)"), 'ASC')
            ->orderBy('city_name', 'ASC');
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
     * @return  string
     */
    public function setUrl($pageName, Controller $controller) {
        $params = [
            'event_id' => $this->id . '-' . Str::slug($this->name)
        ];

        return $this->url = $controller->pageUrl($pageName, $params, false);
    }

}
