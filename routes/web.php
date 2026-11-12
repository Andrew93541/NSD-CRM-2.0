<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RaBillController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WorkerPortalController;
use App\Http\Controllers\WorkforceController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Render / Load Balancer Health Check (minimal response, no sensitive environment details)
Route::get('/healthz', fn () => response()->json(['status' => 'ok'], 200))->name('healthz');

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

// Stateless Site-Bound Kiosk Terminal Routes (Device Token Bound, Rate Limited, No Persistent Worker Session)
Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/{token}', [KioskController::class, 'terminal'])->name('terminal');
    Route::post('/{token}/punch', [KioskController::class, 'punch'])->name('punch');
});

// Authenticated Application Routes
Route::post('/locale', [AuthController::class, 'switchLocale'])->name('switch_locale');
Route::post('/theme', [AuthController::class, 'switchTheme'])->name('switch_theme');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/switch-company', [AuthController::class, 'switchCompany'])->name('switch_company');
    Route::post('/switch-site', [AuthController::class, 'switchSite'])->name('switch_site');

    // Manager Two-Factor Authentication Challenge & User Security Controls
    Route::get('/two-factor-challenge', [AuthController::class, 'showTwoFactorChallenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [AuthController::class, 'verifyTwoFactorChallenge'])->name('two-factor.verify');
    Route::post('/two-factor-challenge/resend', [AuthController::class, 'resendTwoFactorChallenge'])->name('two-factor.resend');
    Route::post('/users/{user}/toggle-2fa', [AuthController::class, 'toggleTwoFactor'])->name('users.toggle_2fa');
    Route::post('/users/{user}/reset-2fa', [AuthController::class, 'resetTwoFactor'])->name('users.reset_2fa');
    Route::post('/users/{user}/toggle-active', [AuthController::class, 'toggleUserActive'])->name('users.toggle_active');

    // Mandatory / Voluntary Password Update
    Route::get('/account/password', [UserManagementController::class, 'showPasswordChange'])->name('account.password.show');
    Route::post('/account/password', [UserManagementController::class, 'updateOwnPassword'])->name('account.password.update');

    // Enterprise User, Role & Access Management (Company Admin / Authorized Access)
    Route::prefix('admin')->name('admin.')->group(function () {
        // Users & Access
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/permissions', [UserManagementController::class, 'updatePermissions'])->name('users.permissions.update');
        Route::post('/users/{user}/permissions/reset', [UserManagementController::class, 'resetPermissions'])->name('users.permissions.reset');
        Route::put('/users/{user}/sites', [UserManagementController::class, 'updateSites'])->name('users.sites.update');
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])->name('users.password.reset');

        // Roles & Permission Matrix
        Route::get('/roles', [RoleManagementController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleManagementController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleManagementController::class, 'store'])->name('roles.store');
        Route::get('/roles/matrix', [RoleManagementController::class, 'matrix'])->name('roles.matrix');
        Route::put('/roles/matrix', [RoleManagementController::class, 'updateMatrix'])->name('roles.matrix.update');
        Route::get('/roles/{role}/edit', [RoleManagementController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])->name('roles.update');
        Route::post('/roles/{role}/duplicate', [RoleManagementController::class, 'duplicate'])->name('roles.duplicate');
        Route::post('/roles/{role}/toggle-active', [RoleManagementController::class, 'toggleActive'])->name('roles.toggle_active');
        Route::delete('/roles/{role}', [RoleManagementController::class, 'destroy'])->name('roles.destroy');
    });

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
        Route::delete('/{site}', [SiteController::class, 'destroy'])->name('destroy');
        Route::post('/{site}/assignments', [SiteController::class, 'storeAssignment'])->name('assignments.store');
    });

    // Site Tasks, Notes & Notifications
    Route::prefix('tasks')->name('tasks.')->group(function () {
        Route::get('/', [TaskController::class, 'index'])->name('index');
        Route::post('/', [TaskController::class, 'store'])->name('store');
        Route::post('/notes', [TaskController::class, 'storeNote'])->name('notes.store');
        Route::post('/notes/{note}/toggle-pin', [TaskController::class, 'toggleNotePin'])->name('notes.toggle_pin');
        Route::post('/notes/{note}/convert-to-task', [TaskController::class, 'convertNoteToTask'])->name('notes.convert_to_task');
        Route::delete('/notes/{note}', [TaskController::class, 'destroyNote'])->name('notes.destroy');
        Route::get('/notifications', [TaskController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [TaskController::class, 'markAllNotificationsRead'])->name('notifications.read_all');
        Route::post('/notifications/{notification}/read', [TaskController::class, 'markNotificationRead'])->name('notifications.read');
        Route::get('/{task}', [TaskController::class, 'show'])->name('show');
        Route::put('/{task}/status', [TaskController::class, 'updateStatus'])->name('update_status');
        Route::delete('/{task}', [TaskController::class, 'destroy'])->name('destroy');
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
        Route::get('/kiosk', [KioskController::class, 'hub'])->name('kiosk');
        Route::post('/kiosk/{site}/rotate-token', [KioskController::class, 'rotateToken'])->name('kiosk.rotate');
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

    // Payroll, Salary Advances & Payslips Module
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::post('/advances', [PayrollController::class, 'storeAdvance'])->name('advances.store');
        Route::get('/{payrollPeriod}', [PayrollController::class, 'show'])->name('show');
        Route::post('/{payrollPeriod}/recalculate', [PayrollController::class, 'recalculate'])->name('recalculate');
        Route::post('/{payrollPeriod}/lock', [PayrollController::class, 'approveAndLock'])->name('lock');
        Route::post('/{payrollPeriod}/entries/{entry}', [PayrollController::class, 'adjustEntry'])->name('entries.adjust');
        Route::get('/{payrollPeriod}/entries/{entry}/payslip', [PayrollController::class, 'payslip'])->name('payslip');
    });

    // Work Orders Workflow
    Route::prefix('work-orders')->name('work_orders.')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index'])->name('index');
        Route::get('/create', [WorkOrderController::class, 'create'])->name('create');
        Route::post('/', [WorkOrderController::class, 'store'])->name('store');
        Route::post('/clients', [WorkOrderController::class, 'storeClient'])->name('clients.store');

        // Phase G & H: Client PO Upload, Extraction, Review & Automatic Site Creation
        Route::get('/uploads/create', [WorkOrderController::class, 'createUpload'])->name('uploads.create');
        Route::post('/uploads', [WorkOrderController::class, 'storeUpload'])->name('uploads.store');
        Route::get('/uploads/{upload}/review', [WorkOrderController::class, 'reviewUpload'])->name('uploads.review');
        Route::put('/uploads/{upload}/review', [WorkOrderController::class, 'updateUploadReview'])->name('uploads.update_review');
        Route::post('/uploads/{upload}/import-lines', [WorkOrderController::class, 'importUploadLines'])->name('uploads.import_lines');
        Route::post('/uploads/{upload}/confirm', [WorkOrderController::class, 'confirmUpload'])->name('uploads.confirm');
        Route::get('/uploads/{upload}/pdf', [WorkOrderController::class, 'downloadUploadPdf'])->name('uploads.pdf');

        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->name('show');
        Route::post('/{workOrder}/lifecycle', [WorkOrderController::class, 'transitionLifecycle'])->name('lifecycle');
        Route::get('/{workOrder}/print', [WorkOrderController::class, 'printOrder'])->name('print');
        Route::get('/{workOrder}/export-pdf', [WorkOrderController::class, 'exportPdf'])->name('export_pdf');
        Route::post('/{workOrder}/revisions', [WorkOrderController::class, 'storeRevision'])->name('revisions.store');
        Route::post('/{workOrder}/revisions/{revision}/approve', [WorkOrderController::class, 'approveRevision'])->name('revisions.approve');
        Route::post('/{workOrder}/approve-site', [WorkOrderController::class, 'approveSite'])->name('approve_site');
        Route::post('/{workOrder}/approve-senior', [WorkOrderController::class, 'approveSenior'])->name('approve_senior');
        Route::post('/{workOrder}/reject', [WorkOrderController::class, 'reject'])->name('reject');
        Route::post('/{workOrder}/attachments', [WorkOrderController::class, 'storeAttachment'])->name('attachments.store');
        Route::get('/{workOrder}/attachments/{attachment}/download', [WorkOrderController::class, 'downloadAttachment'])->name('attachments.download');
        Route::delete('/{workOrder}/attachments/{attachment}', [WorkOrderController::class, 'destroyAttachment'])->name('attachments.destroy');

        // Phase B & E: Site Measurements & RA Billing Engine
        Route::get('/{workOrder}/measurements/create', [RaBillController::class, 'createMeasurement'])->name('measurements.create');
        Route::post('/{workOrder}/measurements', [RaBillController::class, 'storeMeasurement'])->name('measurements.store');
        Route::get('/{workOrder}/measurements/{measurement}/photo', [RaBillController::class, 'downloadMeasurementPhoto'])->name('measurements.photo');
        Route::get('/{workOrder}/ra-bills/create', [RaBillController::class, 'create'])->name('ra_bills.create');
        Route::post('/{workOrder}/ra-bills', [RaBillController::class, 'store'])->name('ra_bills.store');
        Route::get('/{workOrder}/ra-bills/{raBill}', [RaBillController::class, 'show'])->name('ra_bills.show');
        Route::put('/{workOrder}/ra-bills/{raBill}', [RaBillController::class, 'update'])->name('ra_bills.update');
        Route::post('/{workOrder}/ra-bills/{raBill}/submit', [RaBillController::class, 'submit'])->name('ra_bills.submit');
        Route::post('/{workOrder}/ra-bills/{raBill}/approve-site', [RaBillController::class, 'approveSite'])->name('ra_bills.approve_site');
        Route::post('/{workOrder}/ra-bills/{raBill}/approve-senior', [RaBillController::class, 'approveSenior'])->name('ra_bills.approve_senior');
        Route::post('/{workOrder}/ra-bills/{raBill}/reject', [RaBillController::class, 'reject'])->name('ra_bills.reject');

        // Phase C: Tax Invoice & Abstract PDF + Live-Formula Excel (.xlsx) Exports
        Route::get('/{workOrder}/ra-bills/{raBill}/export/pdf/{exportType}', [RaBillController::class, 'exportPdf'])->name('ra_bills.export_pdf');
        Route::get('/{workOrder}/ra-bills/{raBill}/export/xlsx', [RaBillController::class, 'exportXlsx'])->name('ra_bills.export_xlsx');
        Route::get('/{workOrder}/ra-bills/{raBill}/exports/{export}/download', [RaBillController::class, 'downloadExport'])->name('ra_bills.exports.download');
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
