<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\RefundController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
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

    Route::get('/inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::post('/inventory/adjustments', [InventoryController::class, 'store'])->middleware('permission:inventory.adjust')->name('inventory.adjustments.store');

    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/create', [OrderController::class, 'create'])->middleware('permission:orders.create')->name('orders.create');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:orders.create')->name('orders.store');
    Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->middleware('permission:orders.update')->name('orders.edit');
    Route::put('/orders/{order}', [OrderController::class, 'update'])->middleware('permission:orders.update')->name('orders.update');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::patch('/orders/{order}/status', [OrderStatusController::class, 'update'])->name('orders.status.update');
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('orders.payments.store');
    Route::post('/orders/{order}/returns', [OrderReturnController::class, 'store'])->middleware('permission:returns.create')->name('orders.returns.store');
    Route::post('/orders/{order}/refunds', [RefundController::class, 'store'])->middleware('permission:payments.refund')->name('orders.refunds.store');
});
