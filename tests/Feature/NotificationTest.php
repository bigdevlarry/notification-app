<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $recipient;
    private string $base = '/api/v1/notifications';

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipient = User::factory()->create();
    }

    public function test_can_create_a_notification(): void
    {
        Queue::fake();

        $response = $this->postJson($this->base, $this->payload());

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id',
            'recipient_id',
            'channel',
            'content',
            'priority',
            'status',
            'created_at']
        ]);
        $response->assertJsonPath('data.status', NotificationStatus::Pending->value);
    }

    public function test_can_list_all_notifications(): void
    {
        Notification::factory()->count(3)->create();

        $response = $this->getJson($this->base);

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_show_a_single_notification(): void
    {
        $notification = Notification::factory()->create();

        $response = $this->getJson("{$this->base}/{$notification->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $notification->id);
    }

    public function test_can_delete_a_notification(): void
    {
        $notification = Notification::factory()->create();

        $this->deleteJson("{$this->base}/{$notification->id}")->assertNoContent();
        $this->assertModelMissing($notification);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->postJson($this->base, []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['recipient_id', 'channel', 'content', 'priority']);
    }

    public function test_create_rejects_invalid_channel_and_priority(): void
    {
        $response = $this->postJson($this->base, $this->payload(['channel' => 'fax', 'priority' => 'whenever']));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['channel', 'priority']);
    }

    public function test_show_returns_404_for_unknown_notification(): void
    {
        $this->getJson("{$this->base}/non-existent-id")
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    public function test_can_cancel_a_pending_notification(): void
    {
        $notification = Notification::factory()->create(['status' => NotificationStatus::Pending]);

        $response = $this->patchJson("{$this->base}/{$notification->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath('data.status', NotificationStatus::Cancelled->value);
        $this->assertNotNull($notification->fresh()->cancelled_at);
    }

    public function test_cancel_returns_404_for_unknown_notification(): void
    {
        $this->patchJson("{$this->base}/non-existent-id/cancel")
            ->assertNotFound()
            ->assertJsonPath('message', 'Not found.');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'recipient_id' => $this->recipient->id,
            'channel'      => NotificationChannel::Mail->value,
            'content'      => 'Your order has been shipped.',
            'priority'     => NotificationPriority::High->value,
        ], $overrides);
    }
}
