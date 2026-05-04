<?php

namespace Tests\Unit;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Jobs\ProcessNotificationJob;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NotificationService;
    }

    public function test_create_persists_notification_and_dispatches_job_on_correct_queue(): void
    {
        Queue::fake();

        $recipient = User::factory()->create();

        $this->service->create([
            'recipient_id' => $recipient->id,
            'channel' => NotificationChannel::Mail->value,
            'content' => 'Hello.',
            'priority' => NotificationPriority::High->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $recipient->id,
            'content' => 'Hello.',
        ]);

        Queue::assertPushedOn('notifications-high', ProcessNotificationJob::class);
    }

    public function test_duplicate_payload_returns_existing_notification_without_dispatching_again(): void
    {
        Queue::fake();

        $recipient = User::factory()->create();

        $payload = [
            'recipient_id' => $recipient->id,
            'channel' => NotificationChannel::Mail->value,
            'content' => 'Once.',
            'priority' => NotificationPriority::Normal->value,
        ];

        $first = $this->service->create($payload);
        $second = $this->service->create($payload);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(ProcessNotificationJob::class, 1);
    }

    public function test_create_batch_persists_all_notifications_under_same_batch_id(): void
    {
        Bus::fake();

        $recipient = User::factory()->create();

        $batchId = $this->service->createBatch([
            [
                'recipient_id' => $recipient->id,
                'channel' => NotificationChannel::Mail->value,
                'content' => 'First.',
                'priority' => NotificationPriority::Low->value,
            ],
            [
                'recipient_id' => $recipient->id,
                'channel' => NotificationChannel::Mail->value,
                'content' => 'Second.',
                'priority' => NotificationPriority::High->value,
            ],
        ]);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['batch_id' => $batchId, 'content' => 'First.']);
        $this->assertDatabaseHas('notifications', ['batch_id' => $batchId, 'content' => 'Second.']);
    }
}
