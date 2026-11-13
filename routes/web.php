<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Api\PosApiController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.store');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');

    Route::prefix('api/pos')->name('api.pos.')->group(function () {
        Route::get('/search', [PosApiController::class, 'search'])->name('search');
        Route::get('/customers', [PosApiController::class, 'customerSearch'])->name('customers');
        Route::post('/customers', [PosApiController::class, 'storeCustomer'])->name('customers.store');
        Route::post('/quick-add', [PosApiController::class, 'quickAdd'])->name('quick-add');
        Route::post('/products/{product}/variants', [PosApiController::class, 'addVariants'])->name('products.variants');
        Route::post('/totals', [PosApiController::class, 'totals'])->name('totals');
        Route::post('/sales', [PosApiController::class, 'sale'])->name('sales');
        Route::post('/shift/open', [PosApiController::class, 'openShift'])->name('shift.open');
        Route::post('/shift/close', [PosApiController::class, 'closeShift'])->name('shift.close');
        Route::get('/holds', [PosApiController::class, 'holds'])->name('holds');
        Route::post('/holds', [PosApiController::class, 'hold'])->name('holds.store');
        Route::post('/holds/{hold}/resume', [PosApiController::class, 'resumeHold'])->name('holds.resume');
        Route::delete('/holds/{hold}', [PosApiController::class, 'discardHold'])->name('holds.discard');
    });

    Route::middleware('role:owner,manager,storekeeper')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::patch('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/fitments', [ProductController::class, 'storeFitment'])->name('products.fitments.store');
        Route::delete('/products/{product}/fitments/{fitment}', [ProductController::class, 'destroyFitment'])->name('products.fitments.destroy');
        Route::post('/products/{product}/aliases', [ProductController::class, 'storeAlias'])->name('products.aliases.store');
        Route::delete('/products/{product}/aliases/{alias}', [ProductController::class, 'destroyAlias'])->name('products.aliases.destroy');
    });

    Route::middleware('role:owner,manager,cashier')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });

    Route::middleware('role:owner,manager,cashier,accountant')->group(function () {
        Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::post('/customers/{customer}/collections', [CustomerPaymentController::class, 'store'])->name('customers.collections.store');
        Route::get('/credit-payments/{payment}/receipt', [CustomerPaymentController::class, 'receipt'])->name('credit-payments.receipt');
    });

    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('role:owner,manager,storekeeper')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        Route::get('/inventory/{product}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{product}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('/inventory/{product}/receive', [InventoryController::class, 'receive'])->name('inventory.receive');
    });

    Route::middleware('role:owner,manager,cashier')->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/invoice', [SaleController::class, 'invoice'])->name('sales.invoice');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    });

    Route::middleware('role:owner,manager')->group(function () {
        Route::get('/sales/{sale}/return', [SaleController::class, 'returnForm'])->name('sales.return');
        Route::post('/sales/{sale}/return', [SaleController::class, 'returnStore'])->name('sales.return.store');
        Route::get('/returns/{saleReturn}/credit-note', [SaleController::class, 'creditNote'])->name('returns.credit-note');
    });

    Route::middleware('role:owner,manager,accountant')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/vat', [ReportController::class, 'vat'])->name('reports.vat');
        Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/profit', [ReportController::class, 'profit'])->name('reports.profit');
        Route::get('/reports/cash-credit', [ReportController::class, 'cashCredit'])->name('reports.cash-credit');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    Route::middleware('role:owner')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
    });
});
