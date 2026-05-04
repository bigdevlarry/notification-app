<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationStatusRequest;
use App\Http\Requests\StoreBatchNotificationRequest;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\NotificationCollection;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): NotificationCollection
    {
        $query = Notification::query()
            ->latest()
            ->when($request->filled('status'), fn ($q) => $q->byStatus($request->string('status'))
            )
            ->when($request->filled('channel'), fn ($q) => $q->byChannel($request->string('channel'))
            )
            ->when($request->hasAny(['from', 'to']), fn ($q) => $q->createdBetween($request->input('from'), $request->input('to'))
            );

        return new NotificationCollection(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $notification = $this->service->create($request->validated());

        return (new NotificationResource($notification))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse|NotificationResource
    {
        $notification = Notification::find($id);

        if ($notification === null) {
            return response()->json(['message' => 'Not found.'], HttpResponse::HTTP_NOT_FOUND);
        }

        return new NotificationResource($notification);
    }

    public function storeBatch(StoreBatchNotificationRequest $request): JsonResponse
    {
        $notifications = $request->validated('notifications');

        $batchId = $this->service->createBatch($notifications);

        return response()->json([
            'batch_id' => $batchId,
            'count' => count($notifications),
            'status' => 'queued',
        ], HttpResponse::HTTP_CREATED);
    }

    public function status(NotificationStatusRequest $request): JsonResponse|NotificationCollection
    {
        $notifications = $this->service->getStatus($request->validated());

        if ($notifications->isEmpty()) {
            return response()->json(['message' => 'Not found.'], HttpResponse::HTTP_NOT_FOUND);
        }

        return new NotificationCollection($notifications);
    }

    public function cancel(string $id): JsonResponse|NotificationResource
    {
        $notification = Notification::find($id);

        if ($notification === null) {
            return response()->json(['message' => 'Not found.'], HttpResponse::HTTP_NOT_FOUND);
        }

        if (! $notification->canBeCancelled()) {
            return response()->json(
                ['message' => 'Only pending notifications can be cancelled.'],
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $notification->markAsCancelled();

        Log::info('Notification cancelled', ['channel' => $notification->channel->value]);

        return new NotificationResource($notification->fresh());
    }

    public function cancelBatch(string $batchId): JsonResponse
    {
        $cancelled = Notification::where('batch_id', $batchId)
            ->where('status', NotificationStatus::Pending)
            ->get()
            ->each(fn ($n) => $n->markAsCancelled())
            ->count();

        if ($cancelled === 0) {
            return response()->json(
                ['message' => 'No pending notifications found for this batch.'],
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        Log::info('Batch cancelled', ['batch_id' => $batchId, 'count' => $cancelled]);

        return response()->json(['cancelled' => $cancelled]);
    }

    public function destroy(string $id): JsonResponse|Response
    {
        $notification = Notification::find($id);

        if ($notification === null) {
            return response()->json(['message' => 'Not found.'], HttpResponse::HTTP_NOT_FOUND);
        }

        $notification->delete();

        Log::info('Notification deleted', ['channel' => $notification->channel->value]);

        return response()->noContent();
    }
}
