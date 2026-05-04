<?php

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    public function create(array $data): Notification
    {
        $priority = NotificationPriority::from($data['priority']);

        $notification = Notification::firstOrCreate(
            ['idempotency_key' => $this->generateIdempotencyKey($data)],
            [
                'recipient_id'      => $data['recipient_id'],
                'recipient_address' => $data['recipient_address'] ?? null,
                'channel'           => $data['channel'],
                'content'           => $data['content'],
                'priority'          => $priority->value,
                'status'            => NotificationStatus::Pending->value,
            ]
        );

        if ($notification->wasRecentlyCreated) {
            ProcessNotificationJob::dispatch($notification->id)
                ->onQueue($this->resolveQueue($priority))
                ->afterCommit();
        }

        return $notification;
    }

    public function getStatus(array $data): Collection
    {
        if (!empty($data['id'])) {
            return Notification::where('id', $data['id'])->get();
        }

        return Notification::forBatch($data['batch_id'])->get();
    }

    private function generateIdempotencyKey(array $data): string
    {
        return hash('sha256', implode('|', [
            $data['recipient_id'],
            $data['channel'],
            $data['content'],
            $data['priority'],
        ]));
    }

    private function resolveQueue(NotificationPriority $priority): string
    {
        return match ($priority) {
            NotificationPriority::High   => 'notifications-high',
            NotificationPriority::Normal => 'notifications-normal',
            NotificationPriority::Low    => 'notifications-low',
        };
    }
}
