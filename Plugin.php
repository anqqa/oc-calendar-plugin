<?php namespace Klubitus\Calendar;

use Backend;
use System\Classes\PluginBase;


/**
 * Calendar Plugin Information File
 */
class Plugin extends PluginBase {

    public $require = ['RainLab.User'];


    /**
     * Returns information about this plugin.
     *
     * @return  array
     */
    public function pluginDetails() {
        return [
            'name'        => 'Klubitus Calendar',
            'description' => 'Calendar plugin for Klubitus',
            'author'      => 'Antti Qvickström',
            'icon'        => 'icon-calendar',
            'homepage'    => 'https://github.com/anqqa/klubitus-octobercms-plugins',
        ];
    }


    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return  array
     */
    public function registerComponents() {
        return [
            'Klubitus\Calendar\Components\Events' => 'calendarEvents',
        ];
    }


    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation() {
        return [
            'calendar' => [
                'label'       => 'Calendar',
                'url'         => Backend::url('klubitus/calendar/mycontroller'),
                'icon'        => 'icon-calendar',
                'permissions' => ['klubitus.calendar.*'],
                'order'       => 500,
            ],
        ];
    }

}
