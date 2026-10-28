<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WorkforceController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Application Routes
Route::post('/locale', [AuthController::class, 'switchLocale'])->name('switch_locale');
Route::post('/theme', [AuthController::class, 'switchTheme'])->name('switch_theme');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-company', [AuthController::class, 'switchCompany'])->name('switch_company');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Construction Sites
    Route::prefix('sites')->name('sites.')->group(function () {
        Route::get('/', [SiteController::class, 'index'])->name('index');
        Route::get('/create', [SiteController::class, 'create'])->name('create');
        Route::post('/', [SiteController::class, 'store'])->name('store');
        Route::get('/{site}', [SiteController::class, 'show'])->name('show');
        Route::get('/{site}/edit', [SiteController::class, 'edit'])->name('edit');
        Route::put('/{site}', [SiteController::class, 'update'])->name('update');
    });

    // Workforce & Attendance
    Route::prefix('workforce')->name('workforce.')->group(function () {
        Route::get('/', [WorkforceController::class, 'index'])->name('index');
        Route::get('/create', [WorkforceController::class, 'create'])->name('create');
        Route::post('/', [WorkforceController::class, 'store'])->name('store');
        Route::get('/subcontractors', [WorkforceController::class, 'subcontractors'])->name('subcontractors');
        Route::get('/{worker}', [WorkforceController::class, 'show'])->name('show');
        Route::get('/{worker}/edit', [WorkforceController::class, 'edit'])->name('edit');
        Route::put('/{worker}', [WorkforceController::class, 'update'])->name('update');
        Route::post('/{worker}/documents', [WorkforceController::class, 'storeDocuments'])->name('documents.store');
        Route::get('/{worker}/documents/{document}/download', [WorkforceController::class, 'downloadDocument'])->name('documents.download');
        Route::delete('/{worker}/documents/{document}', [WorkforceController::class, 'destroyDocument'])->name('documents.destroy');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/muster', [AttendanceController::class, 'muster'])->name('muster');
        Route::post('/muster', [AttendanceController::class, 'storeMuster'])->name('store_muster');
        Route::get('/conflicts', [AttendanceController::class, 'conflicts'])->name('conflicts');
        Route::post('/{attendance}/correction', [AttendanceController::class, 'requestCorrection'])->name('request_correction');
        Route::get('/corrections', [AttendanceController::class, 'corrections'])->name('corrections');
        Route::post('/corrections/{correction}/review', [AttendanceController::class, 'reviewCorrection'])->name('review_correction');
    });

    // Work Orders Workflow
    Route::prefix('work-orders')->name('work_orders.')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index'])->name('index');
        Route::get('/create', [WorkOrderController::class, 'create'])->name('create');
        Route::post('/', [WorkOrderController::class, 'store'])->name('store');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->name('show');
        Route::post('/{workOrder}/approve-site', [WorkOrderController::class, 'approveSite'])->name('approve_site');
        Route::post('/{workOrder}/approve-senior', [WorkOrderController::class, 'approveSenior'])->name('approve_senior');
        Route::post('/{workOrder}/reject', [WorkOrderController::class, 'reject'])->name('reject');
    });

    // Procurement & Materials
    Route::prefix('procurement')->name('procurement.')->group(function () {
        Route::get('/requests', [ProcurementController::class, 'requests'])->name('requests');
        Route::get('/requests/create', [ProcurementController::class, 'createRequest'])->name('create_request');
        Route::post('/requests', [ProcurementController::class, 'storeRequest'])->name('store_request');
        Route::post('/requests/{purchaseRequest}/approve', [ProcurementController::class, 'approveRequest'])->name('approve_request');
        Route::get('/requests/{purchaseRequest}/create-po', [ProcurementController::class, 'createOrderForm'])->name('create_po');
        Route::post('/requests/{purchaseRequest}/store-po', [ProcurementController::class, 'storeOrder'])->name('store_po');
        
        Route::get('/orders', [ProcurementController::class, 'orders'])->name('orders');
        Route::get('/orders/{purchaseOrder}/receive', [ProcurementController::class, 'receiveGoodsForm'])->name('receive_goods');
        Route::post('/orders/{purchaseOrder}/confirm-receipt', [ProcurementController::class, 'confirmReceipt'])->name('confirm_receipt');
    });

    // Inventory Management
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/stocks', [InventoryController::class, 'stocks'])->name('stocks');
        Route::get('/transactions', [InventoryController::class, 'transactions'])->name('transactions');
    });

    // Operational Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/workforce', [ReportsController::class, 'workforce'])->name('workforce');
        Route::get('/work-orders', [ReportsController::class, 'workOrders'])->name('work_orders');
        Route::get('/inventory', [ReportsController::class, 'inventory'])->name('inventory');
    });
});
