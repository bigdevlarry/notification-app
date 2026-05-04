<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $recipient;
    private string $base = '/api/v1/notifications';

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipient = User::factory()->create();
    }

    public function test_can_create_a_batch_and_all_notifications_share_the_same_batch_id(): void
    {
        Bus::fake();

        $response = $this->postJson("{$this->base}/batch", [
            'notifications' => array_map(fn ($i) => [
                'recipient_id' => $this->recipient->id,
                'channel'      => NotificationChannel::Mail->value,
                'content'      => "Batch notification {$i}.",
                'priority'     => NotificationPriority::Normal->value,
            ], range(0, 2)),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['batch_id', 'count', 'status']);
        $response->assertJsonPath('count', 3);
        $response->assertJsonPath('status', 'queued');

        $batchId = $response->json('batch_id');

        $this->assertDatabaseCount('notifications', 3);
        $this->assertEquals(3, Notification::where('batch_id', $batchId)->count());
    }

    public function test_can_cancel_all_pending_notifications_in_a_batch(): void
    {
        $batchId = Str::uuid()->toString();

        Notification::factory()->count(3)->create([
            'batch_id' => $batchId,
            'status'   => NotificationStatus::Pending,
        ]);

        $response = $this->patchJson("{$this->base}/batch/{$batchId}/cancel");

        $response->assertOk();
        $response->assertJsonPath('cancelled', 3);
        $this->assertEquals(0, Notification::where('batch_id', $batchId)
            ->where('status', NotificationStatus::Pending)
            ->count());
    }
}
