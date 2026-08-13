<?php

namespace App\Console\Commands;

use App\Mail\AvailabilityAlertMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CheckApplicationAvailabilityCommand extends Command
{
    protected $signature = 'app:check-availability {--url=} {--to=}';

    protected $description = 'Check the public application URL and email when it goes down or recovers';

    public function handle(): int
    {
        if (! config('availability.enabled')) {
            $this->components->info('Availability monitor is disabled.');

            return self::SUCCESS;
        }

        $url = trim((string) ($this->option('url') ?: config('availability.url')));
        $recipient = trim((string) ($this->option('to') ?: config('availability.recipient')));

        if ($url === '' || $recipient === '') {
            $this->components->error('The monitor URL and recipient email must be configured.');

            return self::FAILURE;
        }

        [$available, $reason] = $this->check($url);
        $statePath = storage_path('app/availability-monitor.json');
        $state = $this->readState($statePath);
        $threshold = max(1, (int) config('availability.failure_threshold', 2));
        $checkedAt = now(config('app.timezone'))->format('d/m/Y H:i:s T');

        if ($available) {
            if ($state['alerted']) {
                Mail::to($recipient)->send(new AvailabilityAlertMail($url, true, $checkedAt));
                $this->components->info('Recovery email sent.');
            } else {
                $this->components->info('Application is available.');
            }

            $this->writeState($statePath, ['failures' => 0, 'alerted' => false]);

            return self::SUCCESS;
        }

        $state['failures']++;
        $this->writeState($statePath, $state);

        if (! $state['alerted'] && $state['failures'] >= $threshold) {
            Mail::to($recipient)->send(new AvailabilityAlertMail($url, false, $checkedAt, $reason));
            $state['alerted'] = true;
            $this->writeState($statePath, $state);
            $this->components->error('Application is unavailable; alert email sent.');
        } else {
            $this->components->warn("Availability check failed ({$state['failures']}/{$threshold}): {$reason}");
        }

        return self::FAILURE;
    }

    private function check(string $url): array
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(max(1, (int) config('availability.timeout', 10)))
                ->withHeaders(['User-Agent' => 'Vestapp availability monitor'])
                ->get($url);

            return $response->status() >= 200 && $response->status() < 400
                ? [true, null]
                : [false, "HTTP {$response->status()}"];
        } catch (Throwable $exception) {
            return [false, $exception->getMessage()];
        }
    }

    private function readState(string $path): array
    {
        if (! is_file($path)) {
            return ['failures' => 0, 'alerted' => false];
        }

        $state = json_decode((string) file_get_contents($path), true);

        return [
            'failures' => max(0, (int) ($state['failures'] ?? 0)),
            'alerted' => (bool) ($state['alerted'] ?? false),
        ];
    }

    private function writeState(string $path, array $state): void
    {
        file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX);
    }
}
