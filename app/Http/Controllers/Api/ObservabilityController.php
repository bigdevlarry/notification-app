<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ObservabilityController extends Controller
{
    public function health(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $dbStatus = 'ok';
        } catch (\Throwable) {
            $dbStatus = 'error';
        }

        try {
            $queueDepth = DB::table('jobs')->count();
        } catch (\Throwable) {
            $queueDepth = null;
        }

        $threshold   = config('observability.queue_threshold', 1000);
        $queueStatus = $queueDepth !== null && $queueDepth <= $threshold ? 'ok' : 'backlogged';
        $status      = $dbStatus === 'ok' && $queueStatus === 'ok' ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => [
                'database' => $dbStatus,
                'queue'    => $queueStatus,
            ],
        ], $status === 'ok' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    public function metrics(): JsonResponse
    {
        $data = Cache::remember('metrics', 30, function () {
            $counts = Notification::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $total     = $counts->sum();
            $sent      = $counts->get(NotificationStatus::Sent->value, 0);
            $failed    = $counts->get(NotificationStatus::Failed->value, 0);
            $pending   = $counts->get(NotificationStatus::Pending->value, 0);
            $cancelled = $counts->get(NotificationStatus::Cancelled->value, 0);

            $avgLatencyMs = Notification::whereNotNull('sent_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (sent_at - created_at)) * 1000) as avg_ms')
                ->value('avg_ms');

            $queueDepth = DB::table('jobs')
                ->selectRaw('queue, COUNT(*) as depth')
                ->groupBy('queue')
                ->pluck('depth', 'queue');

            return [
                'notifications' => [
                    'total'        => $total,
                    'pending'      => $pending,
                    'sent'         => $sent,
                    'failed'       => $failed,
                    'cancelled'    => $cancelled,
                    'success_rate' => $total > 0 ? round($sent / $total * 100, 2) : 0,
                    'failure_rate' => $total > 0 ? round($failed / $total * 100, 2) : 0,
                ],
                'latency' => [
                    'avg_ms' => $avgLatencyMs ? round($avgLatencyMs, 2) : null,
                ],
                'queue_depth' => $queueDepth,
            ];
        });

        return response()->json($data);
    }
}
