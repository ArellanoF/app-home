<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushKeysCommand extends Command
{
    protected $signature = 'webpush:generate-vapid-keys';

    protected $description = 'Generate VAPID keys for Web Push notifications';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('WEB_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('WEB_PUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
