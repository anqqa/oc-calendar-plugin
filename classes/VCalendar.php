<?php namespace Klubitus\Calendar\Classes;

use Cache;
use Carbon\Carbon;
use October\Rain\Exception\SystemException;
use October\Rain\Network\Http;

class VCalendar {

    private static function parseDate($dateString) {
        return Carbon::createFromFormat('Ymd\THis\Z', $dateString, 'Europe/London');
    }


    /**
     * Load vCalendar from URL and parse into an array.
     *
     * @param  string  $url
     * @param  bool    $skipCache
     * @return  array
     * @throws  SystemException
     */
    public static function parseEventsFrom($url, $skipCache = false) {
        $url = str_replace('webcal://', 'https://', $url);
        $cacheKey = 'VCalendar::' . md5($url);
        $vcalendar = $skipCache ? null : Cache::get($cacheKey);

        if (!$vcalendar) {
            $response = Http::get($url);

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

            // Parse dates
            if (in_array($property, [ 'CREATED', 'DTEND', 'DTSTAMP', 'DTSTART', 'LAST-MODIFIED' ])) {
                $value = self::parseDate($value);
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

}
