<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Throwable;

class GoogleCalendarService
{
    /**
     * @return array{events: array<int, array<string, mixed>>, synced: bool, calendar: string|null}
     */
    public function upcoming(int $limit = 5, ?CarbonImmutable $fromDate = null): array
    {
        $url = config('services.google_calendar.ical_url');

        if (! $url) {
            return ['events' => [], 'synced' => false, 'calendar' => null];
        }

        try {
            $ical = Cache::remember(
                'google-calendar.ical-feed',
                now()->addMinutes(10),
                fn () => Http::timeout(10)->retry(2, 250)->get($url)->throw()->body(),
            );
            $calendar = Reader::read($ical);
            $timezone = new \DateTimeZone(config('app.timezone', 'Europe/Madrid'));
            $from = ($fromDate ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
            $until = $from->copy()->addYear();
            $events = [];
            $uids = [];

            foreach ($calendar->select('VEVENT') as $component) {
                $uid = (string) $component->UID;

                if ($uid === '' || isset($uids[$uid])) {
                    continue;
                }

                $uids[$uid] = true;
                $iterator = new EventIterator($calendar, $uid, $timezone);
                $iterator->fastForward($from);
                $iterations = 0;

                while ($iterator->valid() && $iterations++ < 100) {
                    $start = CarbonImmutable::instance($iterator->getDtStart())->setTimezone($timezone);

                    if ($start->greaterThan($until)) {
                        break;
                    }

                    $end = CarbonImmutable::instance($iterator->getDtEnd())->setTimezone($timezone);
                    $event = $iterator->getEventObject();

                    if ($end->greaterThanOrEqualTo($from)) {
                        $events[] = [
                            'id' => $uid.'-'.$start->getTimestamp(),
                            'title' => trim((string) ($event->SUMMARY ?? 'Evento sin título')),
                            'location' => trim((string) ($event->LOCATION ?? '')),
                            'start' => $start,
                            'end' => $end,
                            'all_day' => ! $event->DTSTART->hasTime(),
                            'url' => isset($event->URL) ? (string) $event->URL : null,
                        ];
                    }

                    $iterator->next();
                }
            }

            usort($events, fn ($a, $b) => $a['start']->getTimestamp() <=> $b['start']->getTimestamp());

            return [
                'events' => array_slice($events, 0, $limit),
                'synced' => true,
                'calendar' => isset($calendar->{'X-WR-CALNAME'}) ? (string) $calendar->{'X-WR-CALNAME'} : null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['events' => [], 'synced' => false, 'calendar' => null];
        }
    }
}
