<?php

namespace App\Notifications;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as BaseNotification;

class AppNotification extends BaseNotification
{
    use Queueable;

    public function __construct(private readonly Notification $notification) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Notification [{$this->notification->priority->value}]")
            ->line($this->notification->content);
    }
}
