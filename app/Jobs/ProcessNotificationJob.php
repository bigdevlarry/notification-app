<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Jobs\Concerns\SendsNotifications;
use App\Models\Notification;
use App\Services\ExternalNotificationProvider;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ProcessNotificationJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SendsNotifications;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(
        public readonly string $notificationId,
        public readonly ?string $correlationId = null,
    ) {}

    public function handle(ExternalNotificationProvider $provider): void
    {
        Log::withContext(['correlation_id' => $this->correlationId]);

        if ($this->batch()?->cancelled()) {
            return;
        }

        $notification = Notification::findOrFail($this->notificationId);

        if (! $notification->status->isPending()) {
            return;
        }

        $rateLimitKey = "channel:{$notification->channel->value}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 100)) {
            $this->release(30);
            return;
        }

        RateLimiter::hit($rateLimitKey, 1);

        $notification->markAsProcessing();

        $providerMessageId = match ($notification->channel) {
            NotificationChannel::Mail => $this->sendMail($notification),
            NotificationChannel::Sms,
            NotificationChannel::Push => $this->sendViaProvider($notification, $provider),
        };

        $notification->markAsSent($providerMessageId);

        Log::info('Notification sent', ['channel' => $notification->channel->value]);
    }

    public function failed(\Throwable $exception): void
    {
        Notification::find($this->notificationId)
            ?->markAsFailed($exception->getMessage());

        Log::error('Notification failed', ['message' => $exception->getMessage()]);
    }
}
