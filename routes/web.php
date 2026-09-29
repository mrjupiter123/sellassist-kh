<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CodRemittanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryProviderController;
use App\Http\Controllers\InventoryController;
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

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
});
