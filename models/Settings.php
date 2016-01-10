<?php namespace Klubitus\Calendar\Models;

use Model;

/**
 * Settings Model
 */
class Settings extends Model {

    public $implement = ['System.Behaviors.SettingsModel'];

    // A unique code
    public $settingsCode = 'klubitus_calendar_settings';

    // Reference to field configuration
    public $settingsFields = 'fields.yaml';

}
