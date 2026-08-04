<?php

namespace App\Console\Commands;

use App\Models\House;
use App\Models\User;
use Illuminate\Console\Command;

class BootstrapApplicationCommand extends Command
{
    protected $signature = 'app:bootstrap';

    protected $description = 'Create the initial house and administrator from environment variables';

    public function handle(): int
    {
        $email = trim((string) env('ADMIN_EMAIL'));
        $password = (string) env('ADMIN_PASSWORD');

        if ($email === '') {
            $this->components->warn('ADMIN_EMAIL is empty; bootstrap skipped.');

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $this->components->info('Administrator already configured.');

            return self::SUCCESS;
        }

        if ($password === '') {
            $this->components->error('ADMIN_PASSWORD is required when creating the administrator.');

            return self::FAILURE;
        }

        $house = House::firstOrCreate(
            ['name' => env('HOUSE_NAME', 'Mi hogar')],
            [
                'weather_location' => env('WEATHER_LOCATION'),
                'google_calendar_url' => env('GOOGLE_CALENDAR_PUBLIC_URL'),
                'google_calendar_ical_url' => env('GOOGLE_CALENDAR_ICAL_URL'),
            ],
        );

        $user = new User;
        $user->fill([
            'house_id' => $house->id,
            'name' => env('ADMIN_NAME', 'Administrador'),
            'email' => $email,
            'color' => 'sage',
            'is_active' => true,
        ]);
        if ($password !== '') {
            $user->password = $password;
        }
        $user->email_verified_at ??= now();
        $user->save();

        $this->components->info('Administrator created.');

        return self::SUCCESS;
    }
}
