<?php

declare(strict_types=1);

use App\Http\Controllers\DeliveryWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/delivery/webhooks/{provider}', DeliveryWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.delivery.webhooks.receive');
