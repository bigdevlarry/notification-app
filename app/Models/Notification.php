<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string                   $id
 * @property string|null              $batch_id
 * @property string                   $idempotency_key
 * @property int                      $recipient_id
 * @property string|null              $recipient_address
 * @property NotificationChannel      $channel
 * @property string                   $content
 * @property NotificationPriority     $priority
 * @property NotificationStatus       $status
 * @property Carbon|null              $read_at
 * @property Carbon|null              $sent_at
 * @property Carbon|null              $failed_at
 * @property string|null              $failure_reason
 * @property Carbon|null              $cancelled_at
 * @property string|null              $provider_message_id
 * @property Carbon                   $created_at
 * @property Carbon                   $updated_at
 */
class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'batch_id',
        'idempotency_key',
        'recipient_id',
        'recipient_address',
        'channel',
        'content',
        'priority',
        'status',
        'read_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'cancelled_at',
        'provider_message_id',
    ];

    protected $casts = [
        'channel'      => NotificationChannel::class,
        'priority'     => NotificationPriority::class,
        'status'       => NotificationStatus::class,
        'read_at'      => 'datetime',
        'sent_at'      => 'datetime',
        'failed_at'    => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => NotificationStatus::Processing]);
    }

    public function markAsSent(?string $providerMessageId = null): void
    {
        $this->update([
            'status'              => NotificationStatus::Sent,
            'sent_at'             => now(),
            'provider_message_id' => $providerMessageId,
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status'         => NotificationStatus::Failed,
            'failed_at'      => now(),
            'failure_reason' => $reason,
        ]);
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public function canBeCancelled(): bool
    {
        return $this->status->isPending();
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => NotificationStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function scopeForRecipient($query, int $recipientId): void
    {
        $query->where('recipient_id', $recipientId);
    }

    public function scopeByStatus($query, string $status): void
    {
        $query->where('status', $status);
    }

    public function scopeByChannel($query, string $channel): void
    {
        $query->where('channel', $channel);
    }

    public function scopeByPriority($query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    public function scopeCreatedBetween($query, ?string $from, ?string $to): void
    {
        $query->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
              ->when($to,   fn ($q) => $q->whereDate('created_at', '<=', $to));
    }
}
