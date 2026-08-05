<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function taskAssigned(Task $task, User $creator): void
    {
        $publicKey = config('services.web_push.public_key');
        $privateKey = config('services.web_push.private_key');

        if (! $publicKey || ! $privateKey) {
            Log::notice('Web Push omitido: faltan las claves VAPID.');

            return;
        }

        $task->loadMissing('assignee.pushSubscriptions');
        if (! $task->assignee) {
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
            'title' => 'Nueva tarea para ti',
            'body' => "{$creator->name} te ha asignado «{$task->title}».",
            'icon' => asset('images/logo.png'),
            'badge' => asset('images/logo.png'),
            'url' => route('home').'#task-'.$task->id,
            'tag' => 'task-'.$task->id,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ($task->assignee->pushSubscriptions as $storedSubscription) {
            $webPush->queueNotification(new Subscription(
                $storedSubscription->endpoint,
                $storedSubscription->public_key,
                $storedSubscription->auth_token,
                $storedSubscription->content_encoding,
            ), $payload);
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $task->assignee->pushSubscriptions()
                    ->where('endpoint_hash', hash('sha256', $report->getEndpoint()))
                    ->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('No se pudo enviar una notificación Web Push.', [
                    'user_id' => $task->user_id,
                    'reason' => $report->getReason(),
                ]);
            }
        }
    }
}
