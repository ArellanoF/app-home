<?php

namespace App\Console\Commands;

use App\Mail\WeeklyPendingTasksMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklyTaskSummariesCommand extends Command
{
    protected $signature = 'tasks:send-weekly-summary
        {--to= : Send only to this active member email address}';

    protected $description = 'Send each active member their pending task summary';

    public function handle(): int
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $sent = 0;

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('tasks', fn ($query) => $query->whereNull('completed_at'))
            ->with(['tasks' => fn ($query) => $query
                ->whereNull('completed_at')
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')]);

        if ($email = $this->option('to')) {
            $users->where('email', $email);
        }

        $users->eachById(function (User $user) use ($today, &$sent) {
                Mail::to($user)->send(new WeeklyPendingTasksMail($user, $user->tasks, $today));
                $sent++;
            });

        if ($email && $sent === 0) {
            $this->warn('No se encontro un miembro activo con ese email y tareas pendientes.');

            return self::FAILURE;
        }

        $this->info("Resumenes semanales enviados: {$sent}.");

        return self::SUCCESS;
    }
}
