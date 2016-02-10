<?php namespace Klubitus\Calendar\Models;

use Db;
use Klubitus\Calendar\Models\Event as EventModel;
use Model;


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

}
