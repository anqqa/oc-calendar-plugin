<?php namespace Klubitus\Calendar\Classes;

use Cache;
use Carbon\Carbon;
use Klubitus\Calendar\Models\Event;
use October\Rain\Exception\SystemException;
use October\Rain\Network\Http;


class VCalendar {

    /**
     * Load vCalendar from URL and parse into an array.
     *
     * @param   string  $url
     * @param   bool    $skipCache
     * @return  array
     * @throws  SystemException
     */
    public static function getFromUrl($url, $skipCache = false) {
        $url = str_replace('webcal://', 'https://', $url);
        $cacheKey = 'VCalendar::' . md5($url);
        $vcalendar = $skipCache ? null : Cache::get($cacheKey);

        if (!$vcalendar) {
            $response = Http::get($url, function(Http $http) {
                $http->setOption(CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.0 Safari/537.36');
            });

            if ($response->code != 200) {
                throw new SystemException('Could not load vcalendar from URL', $response->code);
            }

            $vcalendar = $response->body;

            Cache::put($cacheKey, $vcalendar, 60);
        }

        // Glue multiple line values and split for parsing
        $lines = explode("\r\n", str_replace(['\,', "\r\n "], [',', ''], $vcalendar));

        $events = [];
        $event = false;
        foreach ($lines as $line) {
            if ($line == 'BEGIN:VEVENT') {

                // Start parsing new event
                $event = [];
                continue;

            }
            else if ($event === false) {

                // Skip line if we're not parsing an event
                continue;

            }
            else if ($line == 'END:VEVENT') {

                // Event parsed
                $events[] = $event;
                $event = false;
                continue;

            }

            list($property, $value) = explode(':', $line, 2);

            $propertyParts = explode(';', $property);
            $property = array_shift($propertyParts);

            // Parse data
            switch ($property) {

                case 'CREATED':
                case 'DTEND':
                case 'DTSTAMP':
                case 'DTSTART':
                case 'LAST-MODIFIED':
                    $value = self::parseDate($value);
                    break;

                case 'UID':
                    $event['ID'] = self::parseId($value);
                    break;

                case 'DESCRIPTION':
                    $value = str_replace('\n', "\n", $value);
                    break;

            }

            if (count($propertyParts)) {
                $properties = [];

                foreach ($propertyParts as $part) {
                    list($partName, $partValue) = explode('=', $part);
                    $properties[$partName] = $partValue;
                }

                $event[$property] = [
                    'properties' => $properties,
                    'value'      => $value
                ];
            }
            else {
                $event[$property] = $value;
            }

        }

        return $events;
    }


    /**
     * Parse vCalendar date string to Carbon.
     *
     * @param   string  $dateString
     * @return  Carbon
     */
    private static function parseDate($dateString) {
        return Carbon::createFromFormat('Ymd\THis\Z', $dateString, 'Europe/London');
    }


    /**
     * Parse Event model from vEvent array.
     *
     * @param   array $eventArray
     * @return  Event
     */
    public static function parseEvent(array $eventArray) {
        $event = Event::make([
            'name'               => $eventArray['SUMMARY'],
            'url'                => $eventArray['URL'],
            'begins_at'          => $eventArray['DTSTART'],
            'ends_at'            => $eventArray['DTEND'],
            'info'               => $eventArray['DESCRIPTION'] ?: null,
            'updated_at'         => $eventArray['DTSTAMP'],
            'venue_name'         => $eventArray['LOCATION'] ?: null,
            'facebook_id'        => $eventArray['ID'],
            'facebook_organizer' => $eventArray['ORGANIZER']['properties']['CN'],
            'price'              => null,
        ]);

        return $event;
    }


    /**
     * Parse Event models from vEvents array.
     *
     * @param   array $eventsArray
     * @return  Event[]
     */
    public static function parseEvents(array $eventsArray) {
        return array_map('self::parseEvent', $eventsArray);
    }


    /**
     * Parse Facebook event id from uid.
     *
     * @param   string  $uid
     * @return  string
     */
    public static function parseId($uid) {
        if (preg_match('/^e(\d+)@/', $uid, $matches)) {
            return $matches[1];
        }

        return null;
    }

}
