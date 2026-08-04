<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('tasks:send-weekly-summary')
    ->mondays()
    ->at('08:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
