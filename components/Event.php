<?php namespace Klubitus\Calendar\Components;

use Auth;
use Exception;
use Cms\Classes\ComponentBase;
use Flash;
use Klubitus\Calendar\Models\Event as EventModel;
use Klubitus\Calendar\Models\Favorite as FavoriteModel;
use Lang;
use RainLab\User\Models\User;


class Event extends ComponentBase {

    /**
     * @var  EventModel
     */
    public $event;


    public function componentDetails() {
        return [
            'name'        => 'Single Event',
            'description' => 'Single event partials.'
        ];
    }


    public function defineProperties() {
        return [
            'id' => [
                'title'   => 'Event Id',
                'default' => '{{ :event_id }}',
                'type'    => 'string',
            ],
        ];
    }


    /**
     * Add an event to favorites.
     */
    public function onAddFavorite() {
        try {
            if (!$user = $this->user()) {
                return;
            }

            $eventId = (int)post('id');

            FavoriteModel::create([
                'user_id'  => $user->id,
                'event_id' => $eventId
            ]);

            $this->page['event'] = EventModel::findOrFail($eventId);
            $this->page['user'] = $user;

            Flash::success(Lang::get('klubitus.calendar::lang.favorite.added'));
        } catch (Exception $e) {
            Flash::error(Lang::get('klubitus.calendar::lang.favorite.add_failed'));
        }
    }


    /**
     * Remove an event from favorites.
     */
    public function onRemoveFavorite() {
        try {
            if (!$user = $this->user()) {
                return;
            }

            $eventId = (int)post('id');

            $favorite = FavoriteModel::where('event_id', $eventId)
                ->where('user_id', $user->id)
                ->firstOrFail();
            $favorite->delete();

            $this->page['event'] = $event = EventModel::findOrFail($eventId);
            $this->page['user'] = $user;

            Flash::success(Lang::get('klubitus.calendar::lang.favorite.removed'));
        } catch (Exception $e) {
            Flash::error(Lang::get('klubitus.calendar::lang.favorite.remove_failed'));
        }
    }


    public function onRun() {
        $this->page['event']
            = $this->event
            = EventModel::with('flyers.image', 'venue')->findOrFail((int)$this->property('id'));
    }


    /**
     * Authenticated user, if any.
     *
     * @return  User|null
     */
    public function user() {
        if (!Auth::check()) {
            return null;
        }

        return Auth::getUser();
    }

}
