<?php

declare(strict_types=1);

use App\Http\Controllers\DeliveryWebhookController;
use App\Http\Controllers\MetaWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/delivery/webhooks/{provider}', DeliveryWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.delivery.webhooks.receive');

Route::get('/social/meta/webhook', [MetaWebhookController::class, 'verify'])
    ->middleware('throttle:60,1')
    ->name('api.social.meta.webhook.verify');
Route::post('/social/meta/webhook', [MetaWebhookController::class, 'receive'])
    ->middleware('throttle:120,1')
    ->name('api.social.meta.webhook.receive');
