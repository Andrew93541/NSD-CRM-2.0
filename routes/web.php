<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkerPortalController;
use App\Http\Controllers\WorkforceController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Direct Employee Worker Portal Routes (Worker Code + 6-digit PIN)
Route::prefix('worker')->name('worker.')->group(function () {
    Route::get('/login', [WorkerPortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [WorkerPortalController::class, 'login'])->name('login.post');
    Route::post('/logout', [WorkerPortalController::class, 'logout'])->name('logout');
    Route::get('/pin', [WorkerPortalController::class, 'showPinChange'])->name('pin.show');
    Route::post('/pin', [WorkerPortalController::class, 'updatePin'])->name('pin.update');
    Route::get('/punch', [WorkerPortalController::class, 'punchScreen'])->name('punch');
    Route::post('/punch', [WorkerPortalController::class, 'storePunch'])->name('punch.store');
    Route::post('/leave', [WorkerPortalController::class, 'storeLeaveRequest'])->name('leave.store');
});

// Authenticated Application Routes
Route::post('/locale', [AuthController::class, 'switchLocale'])->name('switch_locale');
Route::post('/theme', [AuthController::class, 'switchTheme'])->name('switch_theme');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-company', [AuthController::class, 'switchCompany'])->name('switch_company');
    Route::post('/switch-site', [AuthController::class, 'switchSite'])->name('switch_site');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Construction Projects
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::post('/{project}/milestones', [ProjectController::class, 'storeMilestone'])->name('milestones.store');
        Route::put('/{project}/milestones/{milestone}', [ProjectController::class, 'updateMilestone'])->name('milestones.update');
        Route::post('/{project}/documents', [ProjectController::class, 'storeDocument'])->name('documents.store');
        Route::get('/{project}/documents/{document}/download', [ProjectController::class, 'downloadDocument'])->name('documents.download');
        Route::delete('/{project}/documents/{document}', [ProjectController::class, 'destroyDocument'])->name('documents.destroy');
    });

    // Construction Sites
    Route::prefix('sites')->name('sites.')->group(function () {
        Route::get('/', [SiteController::class, 'index'])->name('index');
        Route::get('/create', [SiteController::class, 'create'])->name('create');
        Route::post('/', [SiteController::class, 'store'])->name('store');
        Route::get('/{site}', [SiteController::class, 'show'])->name('show');
        Route::get('/{site}/edit', [SiteController::class, 'edit'])->name('edit');
        Route::put('/{site}', [SiteController::class, 'update'])->name('update');
        Route::post('/{site}/assignments', [SiteController::class, 'storeAssignment'])->name('assignments.store');
    });

    // Site Tasks, Notes & Notifications
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('index');
        Route::post('/', [TaskController::class, 'store'])->name('store');
        Route::post('/notes', [TaskController::class, 'storeNote'])->name('notes.store');
        Route::get('/notifications', [TaskController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [TaskController::class, 'markAllNotificationsRead'])->name('notifications.read_all');
        Route::post('/notifications/{notification}/read', [TaskController::class, 'markNotificationRead'])->name('notifications.read');
        Route::get('/{task}', [TaskController::class, 'show'])->name('show');
        Route::put('/{task}/status', [TaskController::class, 'updateStatus'])->name('update_status');
        Route::post('/{task}/comments', [TaskController::class, 'storeComment'])->name('comments.store');
    });

    // Workforce & Attendance
    Route::prefix('workforce')->name('workforce.')->group(function () {
        Route::get('/', [WorkforceController::class, 'index'])->name('index');
        Route::get('/import', [WorkforceController::class, 'showImportForm'])->name('import');
        Route::get('/import/template', [WorkforceController::class, 'downloadCsvTemplate'])->name('import.template');
        Route::post('/import', [WorkforceController::class, 'importCsv'])->name('import.store');
        Route::get('/create', [WorkforceController::class, 'create'])->name('create');
        Route::post('/', [WorkforceController::class, 'store'])->name('store');
        Route::get('/subcontractors', [WorkforceController::class, 'subcontractors'])->name('subcontractors');
        Route::get('/{worker}', [WorkforceController::class, 'show'])->name('show');
        Route::get('/{worker}/edit', [WorkforceController::class, 'edit'])->name('edit');
        Route::put('/{worker}', [WorkforceController::class, 'update'])->name('update');
        Route::post('/{worker}/transfer', [WorkforceController::class, 'transferSite'])->name('transfer');
        Route::post('/{worker}/reset-pin', [WorkforceController::class, 'resetPin'])->name('reset_pin');
        Route::post('/{worker}/documents', [WorkforceController::class, 'storeDocuments'])->name('documents.store');
        Route::get('/{worker}/documents/{document}/download', [WorkforceController::class, 'downloadDocument'])->name('documents.download');
        Route::delete('/{worker}/documents/{document}', [WorkforceController::class, 'destroyDocument'])->name('documents.destroy');
    });

    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/register', [AttendanceController::class, 'register'])->name('register');
        Route::get('/muster', [AttendanceController::class, 'muster'])->name('muster');
        Route::post('/muster', [AttendanceController::class, 'storeMuster'])->name('store_muster');
        Route::post('/qr-scan', [AttendanceController::class, 'qrScan'])->name('qr_scan');
        Route::post('/lock', [AttendanceController::class, 'lockDay'])->name('lock');
        Route::get('/approvals', [AttendanceController::class, 'approvals'])->name('approvals');
        Route::post('/flags/{flag}/resolve', [AttendanceController::class, 'resolveFlag'])->name('flags.resolve');
        Route::get('/shifts-leaves', [AttendanceController::class, 'shiftsAndLeaves'])->name('shifts_leaves');
        Route::post('/shifts', [AttendanceController::class, 'storeShift'])->name('shifts.store');
        Route::post('/holidays', [AttendanceController::class, 'storeHoliday'])->name('holidays.store');
        Route::post('/leaves', [AttendanceController::class, 'storeLeaveRequest'])->name('leaves.store');
        Route::post('/leaves/{leaveRequest}/review', [AttendanceController::class, 'reviewLeaveRequest'])->name('leaves.review');
        Route::get('/export-payroll', [AttendanceController::class, 'exportPayrollCsv'])->name('export_payroll');
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
        Route::get('/multi-site', [ReportsController::class, 'multiSiteComparison'])->name('multi_site');
        Route::get('/audit-logs', [ReportsController::class, 'auditLogs'])->name('audit_logs');
        Route::get('/audit-logs/export', [ReportsController::class, 'exportAuditLogsCsv'])->name('audit_logs.export');
        Route::get('/work-orders', [ReportsController::class, 'workOrders'])->name('work_orders');
        Route::get('/inventory', [ReportsController::class, 'inventory'])->name('inventory');
    });
});
