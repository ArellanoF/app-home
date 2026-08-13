<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WeeklyPendingTasksMail extends Mailable
{
    public function __construct(
        public User $recipient,
        public Collection $tasks,
        public CarbonImmutable $today,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tus tareas atrasadas ({$this->tasks->count()})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.weekly-pending-tasks',
            with: [
                'overdueTasks' => $this->tasks->filter(
                    fn ($task) => $task->due_date?->isBefore($this->today),
                ),
                'thisWeekTasks' => $this->tasks->filter(
                    fn ($task) => $task->due_date?->betweenIncluded(
                        $this->today,
                        $this->today->endOfWeek(),
                    ),
                ),
                'laterTasks' => $this->tasks->filter(
                    fn ($task) => ! $task->due_date || $task->due_date->isAfter($this->today->endOfWeek()),
                ),
            ],
        );
    }
}
