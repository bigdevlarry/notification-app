<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchNotificationRequest;
use App\Http\Requests\NotificationStatusRequest;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\NotificationCollection;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Enums\NotificationStatus;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): NotificationCollection
    {
        $query = Notification::query()
            ->latest()
            ->when($request->filled('recipient_id'), fn ($q) =>
                $q->forRecipient($request->integer('recipient_id'))
            )
            ->when($request->filled('status'), fn ($q) =>
                $q->byStatus($request->string('status'))
            )
            ->when($request->filled('channel'), fn ($q) =>
                $q->byChannel($request->string('channel'))
            )
            ->when($request->filled('priority'), fn ($q) =>
                $q->byPriority($request->string('priority'))
            )
            ->when($request->hasAny(['from', 'to']), fn ($q) =>
                $q->createdBetween($request->input('from'), $request->input('to'))
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
            ->setStatusCode(201);
    }

    public function show(string $id): NotificationResource
    {
        return new NotificationResource(Notification::findOrFail($id));
    }

    public function markAsRead(string $id): NotificationResource
    {
        $notification = Notification::findOrFail($id);
        $notification->markAsRead();

        return new NotificationResource($notification->fresh());
    }

    public function storeBatch(StoreBatchNotificationRequest $request): JsonResponse
    {
        $batchId = $this->service->createBatch($request->validated('notifications'));

        return response()->json([
            'batch_id' => $batchId,
            'count'    => count($request->validated('notifications')),
            'status'   => 'queued',
        ], 201);
    }

    public function status(NotificationStatusRequest $request): NotificationCollection
    {
        $notifications = $this->service->getStatus($request->validated());

        abort_if($notifications->isEmpty(), 404);

        return new NotificationCollection($notifications);
    }

    public function cancel(string $id): NotificationResource
    {
        $notification = Notification::findOrFail($id);

        abort_if(! $notification->canBeCancelled(), 422, 'Only pending notifications can be cancelled.');

        $notification->markAsCancelled();

        return new NotificationResource($notification->fresh());
    }

    public function cancelBatch(string $batchId): JsonResponse
    {
        $cancelled = Notification::where('batch_id', $batchId)
            ->where('status', NotificationStatus::Pending)
            ->get()
            ->each(fn ($n) => $n->markAsCancelled())
            ->count();

        abort_if($cancelled === 0, 422, 'No pending notifications found for this batch.');

        return response()->json(['cancelled' => $cancelled]);
    }

    public function destroy(string $id): Response
    {
        Notification::findOrFail($id)->delete();

        return response()->noContent();
    }
}
