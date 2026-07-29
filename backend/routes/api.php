<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\CrudController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\HouseholdExcelController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceSettingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RouteExcelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:settings.manage');
        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::get('debts', [DebtController::class, 'index'])->middleware('permission:payments.view');
        Route::get('debts-export', [DebtController::class, 'export'])->middleware('permission:payments.view');
        Route::get('payments/options', [PaymentController::class, 'options'])->middleware('permission:payments.create');
        Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');
        Route::post('invoices/publish', [InvoiceController::class, 'publish'])->middleware('permission:payments.create');
        Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:payments.view');
        Route::get('invoices-export', [InvoiceController::class, 'export'])->middleware('permission:payments.view');
        Route::get('payments/{payment}/receipt', [InvoiceController::class, 'receipt'])->middleware('permission:payments.view');
        Route::get('payments/{payment}/invoice', [InvoiceController::class, 'invoice'])->middleware('permission:payments.view');
        Route::get('invoice-settings', [InvoiceSettingController::class, 'index']);
        Route::put('invoice-settings', [InvoiceSettingController::class, 'update']);
        Route::get('households/{household}/payment-suggestion', [PaymentController::class, 'suggestion'])->middleware('permission:payments.create');
        Route::get('users/options', [UserController::class, 'options']);
        Route::apiResource('users', UserController::class)->except('show');
        Route::get('households-import/template', [HouseholdExcelController::class, 'template']);
        Route::get('households-export', [HouseholdController::class, 'export']);
        Route::post('households-import', [HouseholdExcelController::class, 'import']);
        Route::get('households/options', [HouseholdController::class, 'options']);
        Route::get('households/{household}/payments', [HouseholdController::class, 'payments']);
        Route::post('households/{id}/restore', [HouseholdController::class, 'restore']);
        Route::apiResource('households', HouseholdController::class)->except('show');
        Route::get('routes-import/template', [RouteExcelController::class, 'template']);
        Route::post('routes-import', [RouteExcelController::class, 'import']);
        Route::post('{resource}/{id}/restore', [CrudController::class, 'restore'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'services']);
        Route::get('{resource}', [CrudController::class, 'index'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'services', 'household-services', 'settings']);
        Route::post('{resource}', [CrudController::class, 'store'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'services', 'household-services', 'settings']);
        Route::put('{resource}/{id}', [CrudController::class, 'update'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'services', 'household-services', 'settings']);
        Route::delete('{resource}/{id}', [CrudController::class, 'destroy'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'services', 'household-services', 'settings']);
    });
});
