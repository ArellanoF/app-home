<?php

namespace App\Services;

use App\Models\FamilyNote;
use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function taskAssigned(Task $task, User $creator): void
    {
        $task->loadMissing('assignee.pushSubscriptions');
        if (! $task->assignee) {
            return;
        }

        $this->send($task->assignee->pushSubscriptions, [
            'title' => 'Nueva tarea para ti',
            'body' => "{$creator->name} te ha asignado «{$task->title}».",
            'url' => '/#task-'.$task->id,
            'tag' => 'task-'.$task->id,
        ]);
    }

    public function familyNoteCreated(FamilyNote $note, User $author): void
    {
        $subscriptions = StoredPushSubscription::query()
            ->whereHas('user', fn ($query) => $query
                ->where('house_id', $note->house_id)
                ->where('is_active', true)
                ->whereKeyNot($author->id))
            ->get();

        $this->send($subscriptions, [
            'title' => 'Nuevo aviso en casa',
            'body' => "{$author->name}: ".str($note->content)->squish()->limit(120),
            'url' => '/#avisos',
            'tag' => 'family-note-'.$note->id,
        ]);
    }

    private function send(Collection $subscriptions, array $notification): void
    {
        if ($subscriptions->isEmpty()) {
            return;
        }

        $publicKey = config('services.web_push.public_key');
        $privateKey = config('services.web_push.private_key');

        if (! $publicKey || ! $privateKey) {
            Log::notice('Web Push omitido: faltan las claves VAPID.');

            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.web_push.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ], ['TTL' => 86400, 'urgency' => 'normal']);

        $payload = json_encode([
            ...$notification,
            'icon' => asset('images/logo.png'),
            'badge' => asset('images/logo.png'),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $storedSubscription) {
            $webPush->queueNotification(new Subscription(
                $storedSubscription->endpoint,
                $storedSubscription->public_key,
                $storedSubscription->auth_token,
                $storedSubscription->content_encoding,
            ), $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                StoredPushSubscription::query()
                    ->where('endpoint_hash', hash('sha256', $report->getEndpoint()))
                    ->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('No se pudo enviar una notificación Web Push.', [
                    'endpoint_hash' => hash('sha256', $report->getEndpoint()),
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }
}
