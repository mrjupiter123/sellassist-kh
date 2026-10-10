<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CodRemittanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryProviderController;
use App\Http\Controllers\FailedJobController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentIntegrationController;
use App\Http\Controllers\ShipmentStatusController;
use App\Http\Controllers\SocialAiAlertPreferenceController;
use App\Http\Controllers\SocialAiAlertTestEmailController;
use App\Http\Controllers\SocialAiEvaluationController;
use App\Http\Controllers\SocialAiExtractionProfileController;
use App\Http\Controllers\SocialAiProfileReleaseController;
use App\Http\Controllers\SocialChannelController;
use App\Http\Controllers\SocialContactController;
use App\Http\Controllers\SocialConversationAssignmentController;
use App\Http\Controllers\SocialConversationController;
use App\Http\Controllers\SocialConversationReadController;
use App\Http\Controllers\SocialConversationStatusController;
use App\Http\Controllers\SocialOrderController;
use App\Http\Controllers\SocialOrderExtractionController;
use App\Http\Controllers\SocialOrderExtractionReviewController;
use App\Http\Controllers\SocialReplyController;
use App\Http\Controllers\SocialReplyTemplateController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', EnsureUserIsActive::class])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::put('/notifications/ai-alert-preference', [SocialAiAlertPreferenceController::class, 'update'])->middleware('permission:social.ai.manage')->name('notifications.ai-alert-preference.update');
    Route::post('/notifications/ai-alert-test-email', SocialAiAlertTestEmailController::class)->middleware(['permission:social.ai.manage', 'throttle:3,1'])->name('notifications.ai-alert-test-email');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:orders.view')
        ->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->middleware('permission:customers.create')->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:customers.create')->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->middleware('permission:customers.update')->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update')->name('customers.update');

    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.update')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->name('products.update');
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:products.create')->name('products.variants.store');
    Route::get('/products/{product}/variants/{variant}/edit', [ProductVariantController::class, 'edit'])->middleware('permission:products.update')->name('products.variants.edit');
    Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:products.update')->name('products.variants.update');

    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::post('/inventory/adjustments', [InventoryController::class, 'store'])->middleware('permission:inventory.adjust')->name('inventory.adjustments.store');

    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->middleware('permission:orders.create')->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:orders.create')->name('orders.store');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->middleware('permission:orders.update')->name('orders.edit');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->middleware('permission:orders.update')->name('orders.update');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::get('/orders/{order}/receipt', [OrderController::class, 'receipt'])->middleware('permission:orders.view')->name('orders.receipt');
    Route::patch('/orders/{order}/status', [OrderStatusController::class, 'update'])->name('orders.status.update');
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('orders.payments.store');
    Route::post('/orders/{order}/returns', [OrderReturnController::class, 'store'])->middleware('permission:returns.create')->name('orders.returns.store');
    Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->middleware('permission:payments.refund')->name('orders.refunds.store');
    Route::post('/orders/{order}/shipments', [ShipmentController::class, 'store'])->middleware('permission:delivery.create')->name('orders.shipments.store');

    Route::get('/delivery/shipments', [ShipmentController::class, 'index'])->middleware('permission:delivery.view')->name('delivery.shipments.index');
    Route::get('/delivery/shipments/{shipment}', [ShipmentController::class, 'show'])->middleware('permission:delivery.view')->name('delivery.shipments.show');
    Route::get('/delivery/shipments/{shipment}/label', [ShipmentController::class, 'label'])->middleware('permission:delivery.view')->name('delivery.shipments.label');
    Route::patch('/delivery/shipments/{shipment}/status', [ShipmentStatusController::class, 'update'])->middleware('permission:delivery.update')->name('delivery.shipments.status.update');
    Route::post('/delivery/shipments/{shipment}/submit', [ShipmentIntegrationController::class, 'submit'])->middleware('permission:delivery.update')->name('delivery.shipments.integration.submit');
    Route::post('/delivery/shipments/{shipment}/sync', [ShipmentIntegrationController::class, 'sync'])->middleware('permission:delivery.update')->name('delivery.shipments.integration.sync');
    Route::post('/delivery/shipments/{shipment}/cod-remittances', [CodRemittanceController::class, 'store'])->middleware('permission:delivery.cod.reconcile')->name('delivery.shipments.cod-remittances.store');

    Route::get('/delivery/providers', [DeliveryProviderController::class, 'index'])->middleware('permission:delivery.providers.manage')->name('delivery.providers.index');
    Route::get('/delivery/providers/create', [DeliveryProviderController::class, 'create'])->middleware('permission:delivery.providers.manage')->name('delivery.providers.create');
    Route::post('/delivery/providers', [DeliveryProviderController::class, 'store'])->middleware('permission:delivery.providers.manage')->name('delivery.providers.store');
    Route::get('/delivery/providers/{provider}/edit', [DeliveryProviderController::class, 'edit'])->middleware('permission:delivery.providers.manage')->name('delivery.providers.edit');
    Route::put('/delivery/providers/{provider}', [DeliveryProviderController::class, 'update'])->middleware('permission:delivery.providers.manage')->name('delivery.providers.update');

    Route::get('/social/inbox', [SocialConversationController::class, 'index'])->middleware('permission:social.view')->name('social.inbox.index');
    Route::get('/social/ai-profiles', [SocialAiExtractionProfileController::class, 'index'])->middleware('permission:social.ai.manage')->name('social.ai-profiles.index');
    Route::post('/social/ai-profiles', [SocialAiExtractionProfileController::class, 'store'])->middleware('permission:social.ai.manage')->name('social.ai-profiles.store');
    Route::post('/social/ai-profiles/{profile}/activate', [SocialAiExtractionProfileController::class, 'activate'])->middleware('permission:social.ai.manage')->name('social.ai-profiles.activate');
    Route::get('/social/ai-profile-releases/{release}', [SocialAiProfileReleaseController::class, 'show'])->middleware('permission:social.ai.manage')->name('social.ai-profile-releases.show');
    Route::get('/social/ai-evaluations', [SocialAiEvaluationController::class, 'index'])->middleware('permission:social.ai.manage')->name('social.ai-evaluations.index');
    Route::post('/social/ai-evaluations/cases', [SocialAiEvaluationController::class, 'storeCase'])->middleware('permission:social.ai.manage')->name('social.ai-evaluations.cases.store');
    Route::post('/social/ai-evaluations/datasets', [SocialAiEvaluationController::class, 'storeDataset'])->middleware('permission:social.ai.manage')->name('social.ai-evaluations.datasets.store');
    Route::post('/social/ai-evaluations/{profile}/runs', [SocialAiEvaluationController::class, 'storeRun'])->middleware('permission:social.ai.manage')->name('social.ai-evaluations.runs.store');
    Route::post('/social/ai-evaluations/{profile}/runs/{evaluationRun}/approve', [SocialAiEvaluationController::class, 'approve'])->middleware('permission:social.ai.manage')->scopeBindings()->name('social.ai-evaluations.runs.approve');
    Route::get('/social/inbox/{conversation}', [SocialConversationController::class, 'show'])->middleware('permission:social.view')->name('social.inbox.show');
    Route::post('/social/inbox/{conversation}/link-customer', [SocialContactController::class, 'link'])->middleware('permission:social.manage')->name('social.inbox.link-customer');
    Route::post('/social/inbox/{conversation}/customers', [SocialContactController::class, 'createCustomer'])->middleware('permission:social.manage')->name('social.inbox.customers.store');
    Route::post('/social/inbox/{conversation}/draft-order', [SocialOrderController::class, 'store'])->middleware('permission:social.manage')->name('social.inbox.draft-order.store');
    Route::post('/social/inbox/{conversation}/order-extractions', [SocialOrderExtractionController::class, 'store'])->middleware('permission:social.extract')->name('social.inbox.order-extractions.store');
    Route::put('/social/inbox/{conversation}/order-extractions/{orderExtraction}/review', [SocialOrderExtractionReviewController::class, 'update'])->middleware('permission:social.extract')->scopeBindings()->name('social.inbox.order-extractions.review');
    Route::post('/social/inbox/{conversation}/replies', [SocialReplyController::class, 'store'])->middleware('permission:social.reply')->name('social.inbox.replies.store');
    Route::patch('/social/inbox/{conversation}/assignment', [SocialConversationAssignmentController::class, 'update'])->middleware('permission:social.manage')->name('social.inbox.assignment.update');
    Route::patch('/social/inbox/{conversation}/status', [SocialConversationStatusController::class, 'update'])->middleware('permission:social.manage')->name('social.inbox.status.update');
    Route::delete('/social/inbox/{conversation}/read', [SocialConversationReadController::class, 'destroy'])->middleware('permission:social.view')->name('social.inbox.read.destroy');
    Route::get('/social/channels', [SocialChannelController::class, 'index'])->middleware('permission:social.channels.manage')->name('social.channels.index');
    Route::post('/social/channels', [SocialChannelController::class, 'store'])->middleware('permission:social.channels.manage')->name('social.channels.store');
    Route::put('/social/channels/{channel}', [SocialChannelController::class, 'update'])->middleware('permission:social.channels.manage')->name('social.channels.update');
    Route::get('/social/reply-templates', [SocialReplyTemplateController::class, 'index'])->middleware('permission:social.manage')->name('social.reply-templates.index');
    Route::post('/social/reply-templates', [SocialReplyTemplateController::class, 'store'])->middleware('permission:social.manage')->name('social.reply-templates.store');
    Route::put('/social/reply-templates/{template}', [SocialReplyTemplateController::class, 'update'])->middleware('permission:social.manage')->name('social.reply-templates.update');

    Route::get('/operations', OperationsController::class)->middleware('permission:operations.view')->name('operations.index');
    Route::post('/operations/failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])->middleware('permission:operations.retry')->name('operations.failed-jobs.retry');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
});
