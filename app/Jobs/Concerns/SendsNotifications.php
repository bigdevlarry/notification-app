<?php

namespace App\Jobs\Concerns;

use App\Models\Notification;
use App\Notifications\AppNotification;
use App\Contracts\NotificationProvider;

trait SendsNotifications
{
    private function sendMail(Notification $notification): null
    {
        $notification->recipient->notify(new AppNotification($notification));

        return null;
    }

    private function sendViaProvider(Notification $notification, NotificationProvider $provider): string
    {
        return $provider->send(
            $notification->recipient_address,
            $notification->channel->value,
            $notification->content,
        );
    }
}
