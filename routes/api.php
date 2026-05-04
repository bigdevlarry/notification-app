<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1')->group(function () {
    Route::post('notifications/batch', [NotificationController::class, 'storeBatch'])->name('notifications.batch.store');
    Route::patch('notifications/batch/{batchId}/cancel', [NotificationController::class, 'cancelBatch'])->name('notifications.batch.cancel');
    Route::get('notifications/status', [NotificationController::class, 'status'])->name('notifications.status');

    Route::apiResource('notifications', NotificationController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::patch('notifications/{id}/cancel', [NotificationController::class, 'cancel'])->name('notifications.cancel');
});
