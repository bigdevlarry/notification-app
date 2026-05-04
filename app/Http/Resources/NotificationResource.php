<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'batch_id' => $this->batch_id,
            'recipient_id' => $this->recipient_id,
            'recipient_address' => $this->recipient_address,
            'channel' => $this->channel->value,
            'content' => $this->content,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'sent_at' => $this->sent_at,
            'failed_at' => $this->failed_at,
            'failure_reason' => $this->failure_reason,
            'cancelled_at' => $this->cancelled_at,
            'provider_message_id' => $this->provider_message_id,
            'created_at' => $this->created_at,
        ];
    }
}
