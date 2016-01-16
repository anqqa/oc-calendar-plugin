<?php namespace Klubitus\Calendar;

use Backend;
use Illuminate\Console\Scheduling\Schedule;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use October\Rain\Foundation\Application;
use System\Classes\PluginBase;


/**
 * Calendar Plugin Information File
 */
class Plugin extends PluginBase {

    public $require = [
        'Klubitus.Facebook',
        'RainLab.User'
    ];


    /**
     * Returns information about this plugin.
     *
     * @return  array
     */
    public function pluginDetails() {
        return [
            'name'        => 'Klubitus Calendar',
            'description' => 'Events calendar for Klubitus.',
            'author'      => 'Antti Qvickström',
            'icon'        => 'icon-calendar',
            'homepage'    => 'https://github.com/anqqa/klubitus-octobercms-plugins',
        ];
    }


    public function register() {
        $this->registerConsoleCommand('klubitus.facebookimport', 'Klubitus\Calendar\Console\FacebookImport');
    }


    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return  array
     */
    public function registerComponents() {
        return [
            'Klubitus\Calendar\Components\Events'    => 'calendarEvents',
            'Klubitus\Calendar\Components\EventList' => 'calendarEventList',
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
                'url'         => Backend::url('klubitus/calendar/facebookimport'),
                'icon'        => 'icon-calendar',
                'permissions' => ['klubitus.calendar.*'],
                'order'       => 100,
            ],
        ];
    }


    public function registerSchedule($schedule) {
        /** @var  Schedule  $schedule */
        $schedule->command('klubitus:facebookimport --save')
            ->everyTenMinutes()
            ->when(function() {
                return (bool)CalendarSettings::get('facebook_import_enabled');
            })

            // @TODO: Wait for update to Laravel 5.1 and change this to appendOutputTo
            ->sendOutputTo(Application::getInstance()->storagePath() . '/logs/facebook_import.log');
    }


    public function registerSettings() {
        return [
            'settings' => [
                'label'       => 'Calendar settings',
                'description' => 'Manage calendar settings.',
                'category'    => 'Klubitus',
                'icon'        => 'icon-calendar',
                'class'       => 'Klubitus\Calendar\Models\Settings',
                'order'       => 100,
            ]
        ];
    }

}
