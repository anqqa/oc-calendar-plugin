<?php namespace Klubitus\Calendar\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Klubitus\Calendar\Classes\FacebookImporter;
use Klubitus\Calendar\Models\Settings as CalendarSettings;
use Symfony\Component\Console\Input\InputOption;


class FacebookImport extends Command {
    const OPTION_SAVE    = 'save';
    const OPTION_URL     = 'url';
    const OPTION_USER_ID = 'user';

    /**
     * @var  string  Console command name.
     */
    protected $name = 'klubitus:facebookimport';

    /**
     * @var  string  Console command description.
     */
    protected $description = 'Import events from Facebook using webcal.';


    /**
     * Execute the console command.
     */
    public function fire() {
        $userId = (int)$this->option(self::OPTION_USER_ID);
        $url    = $this->option(self::OPTION_URL);

        if (!$userId || !$url) {
            $this->error('User id or webcal URL is missing.');

            return;
        }

        $importer = new FacebookImporter($userId, $url);

        $result = $importer->import((bool)$this->option(self::OPTION_SAVE));

        $this->info(sprintf('[%s] Facebook import (%d) - added: %d, updated %d, skipped %d',
            Carbon::create()->toDateTimeString(),
            count($result['imported']),
            count($result['added']),
            count($result['updated']),
            count($result['skipped']))
        );

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }
    }


    /**
     * Get console command options.
     *
     * @return  array
     */
    protected function getOptions() {
        return [
            [self::OPTION_SAVE, null, InputOption::VALUE_NONE, 'Save the results, otherwise default do a dry-run'],
            [self::OPTION_URL, null, InputOption::VALUE_REQUIRED, 'Webcal URL',  CalendarSettings::get('facebook_import_url')],
            [self::OPTION_USER_ID, null, InputOption::VALUE_REQUIRED, 'Event author id', CalendarSettings::get('facebook_import_user_id')],
        ];
    }

}
