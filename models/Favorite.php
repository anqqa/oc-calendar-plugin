<?php namespace Klubitus\Calendar\Models;

use Model;

/**
 * Favorite Model
 */
class Favorite extends Model {

    /**
     * @var string The database table used by the model.
     */
    public $table = 'favorites';

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['user_id', 'event_id'];

    /**
     * @var array Relations
     */
    public $belongsTo = [
        'user'  => 'Rainlab\User\Models\User',
        'event' => 'Klubitus\Calendar\Models\Event',
    ];


    public function afterCreate() {
        $this->event->favorite_count++;
        $this->event->save(['timestamps' => false]);
    }


    public function afterDelete() {
        $this->event->favorite_count--;
        $this->event->save(['timestamps' => false]);
    }

}
