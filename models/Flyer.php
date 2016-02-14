<?php namespace Klubitus\Calendar\Models;

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
     * @var string The database table used by the model.
     */
    public $table = 'flyers';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['image', 'event', 'author', 'author_id', 'begins_at', 'name'];

    protected $dates = ['begins_at'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'event'  => 'Klubitus\Calendar\Models\Event',
        'author' => 'RainLab\User\Models\User',
    ];
    public $attachOne = [
        'image' => 'Klubitus\Gallery\Models\File'
    ];


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
     * Get latest models.
     *
     * @param   QueryBuilder  $query
     * @return  QueryBuilder
     */
    public function scopeRecentFlyers($query) {
        return $query->orderBy('created_at', 'desc');
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

        return $this->url = $controller->pageUrl($pageName, $params);
    }

}
