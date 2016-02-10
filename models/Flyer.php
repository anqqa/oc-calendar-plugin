<?php namespace Klubitus\Calendar\Models;

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

}
