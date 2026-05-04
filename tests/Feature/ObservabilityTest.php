<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('metrics');
    }

    public function test_health_check_returns_ok_when_database_is_up(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'checks' => ['database', 'queue']]);
    }

    public function test_metrics_returns_correct_notification_counts(): void
    {
        Notification::factory()->count(3)->sent()->create();
        Notification::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/metrics')->assertOk();

        $this->assertEquals(5, $response->json('notifications.total'));
        $this->assertEquals(3, $response->json('notifications.sent'));
        $this->assertEquals(2, $response->json('notifications.pending'));
    }

    public function test_metrics_returns_correct_success_rate(): void
    {
        Notification::factory()->count(3)->sent()->create();
        Notification::factory()->count(1)->create(['status' => \App\Enums\NotificationStatus::Failed]);

        $response = $this->getJson('/api/v1/metrics')->assertOk();

        $this->assertEquals(75.0, $response->json('notifications.success_rate'));
        $this->assertEquals(25.0, $response->json('notifications.failure_rate'));
    }
}
