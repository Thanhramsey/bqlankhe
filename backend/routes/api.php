<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CrudController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RouteExcelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');
        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.create');
        Route::get('households/{household}/payment-suggestion', [PaymentController::class, 'suggestion'])->middleware('permission:payments.create');
        Route::get('users/options', [UserController::class, 'options']);
        Route::apiResource('users', UserController::class)->except('show');
        Route::get('routes-import/template', [RouteExcelController::class, 'template']);
        Route::post('routes-import', [RouteExcelController::class, 'import']);
        Route::post('{resource}/{id}/restore', [CrudController::class, 'restore'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'households', 'services']);
        Route::get('{resource}', [CrudController::class, 'index'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'households', 'services', 'household-services', 'settings']);
        Route::post('{resource}', [CrudController::class, 'store'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'households', 'services', 'household-services', 'settings']);
        Route::put('{resource}/{id}', [CrudController::class, 'update'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'households', 'services', 'household-services', 'settings']);
        Route::delete('{resource}/{id}', [CrudController::class, 'destroy'])->whereIn('resource', ['provinces', 'wards', 'neighborhoods', 'routes', 'employees', 'households', 'services', 'household-services', 'settings']);
    });
});
