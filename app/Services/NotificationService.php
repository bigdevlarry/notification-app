<?php

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Services\Concerns\ResolvesCorrelationId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    use ResolvesCorrelationId;

    public function create(array $data): Notification
    {
        $priority = NotificationPriority::from($data['priority']);

        $notification = Notification::firstOrCreate(
            ['idempotency_key' => $this->generateIdempotencyKey($data)],
            [
                'recipient_id' => $data['recipient_id'],
                'recipient_address' => $data['recipient_address'] ?? null,
                'channel' => $data['channel'],
                'content' => $data['content'],
                'priority' => $priority->value,
                'status' => NotificationStatus::Pending->value,
            ]
        );

        if ($notification->wasRecentlyCreated) {
            Log::info('Notification created', ['channel' => $data['channel'], 'priority' => $priority->value]);

            ProcessNotificationJob::dispatch($notification->id, $this->correlationId())
                ->onQueue($this->resolveQueue($priority))
                ->afterCommit();
        } else {
            Log::info('Duplicate notification, skipping dispatch', ['channel' => $data['channel']]);
        }

        return $notification;
    }

    public function createBatch(array $notifications): string
    {
        if (count($notifications) > 1000) {
            throw new \InvalidArgumentException('Max 1000 notifications per batch.');
        }

        $batchId = (string) Str::uuid();

        Log::info('Batch notification created', ['batch_id' => $batchId, 'count' => count($notifications)]);

        try {
            $created = DB::transaction(function () use ($notifications, $batchId) {
                return collect($notifications)->map(fn ($data) => Notification::firstOrCreate(
                    ['idempotency_key' => $this->generateIdempotencyKey($data)],
                    [
                        'batch_id' => $batchId,
                        'recipient_id' => $data['recipient_id'],
                        'recipient_address' => $data['recipient_address'] ?? null,
                        'channel' => $data['channel'],
                        'content' => $data['content'],
                        'priority' => NotificationPriority::from($data['priority'])->value,
                        'status' => NotificationStatus::Pending->value,
                    ]
                ));
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create notification batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $correlationId = $this->correlationId();

        $created->groupBy(fn ($n) => $n->priority->value)
            ->each(function ($group, $priorityValue) use ($batchId, $correlationId) {
                $queue = $this->resolveQueue(NotificationPriority::from($priorityValue));

                Bus::batch(
                    $group->map(fn ($n) => new ProcessNotificationJob($n->id, $correlationId))->all()
                )
                    ->onQueue($queue)
                    ->then(fn () => Log::info('Batch completed', ['batch_id' => $batchId, 'queue' => $queue]))
                    ->catch(fn (\Throwable $e) => Log::error('Batch failed', ['batch_id' => $batchId, 'message' => $e->getMessage()]))
                    ->allowFailures()
                    ->dispatch();
            });

        return $batchId;
    }

    public function getStatus(array $data): Collection
    {
        if (! empty($data['id'])) {
            return Notification::where('id', $data['id'])->get();
        }

        return Notification::where('batch_id', $data['batch_id'])->get();
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
            NotificationPriority::High => 'notifications-high',
            NotificationPriority::Normal => 'notifications-normal',
            NotificationPriority::Low => 'notifications-low',
        };
    }
}
