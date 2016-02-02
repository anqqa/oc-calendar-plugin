<?php namespace Klubitus\Calendar\Components;

use Cms\Classes\ComponentBase;
use Klubitus\Calendar\Models\Event as EventModel;


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


    public function onRun() {
        $this->page['event'] = $this->event = EventModel::findOrFail((int)$this->property('id'));
    }

}
