<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('menus', MenuController::class);

    Route::prefix('dashboard')->group(function () {
        Route::get('/daily', [DashboardController::class, 'daily']);
        Route::get('/weekly', [DashboardController::class, 'weekly']);
        Route::get('/monthly', [DashboardController::class, 'monthly']);
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/top-menu', [DashboardController::class, 'topMenu']);
        Route::get('/trend', [DashboardController::class, 'trend']);
        Route::get('/growth', [DashboardController::class, 'growth']);
        Route::get('/best-sales-day', [DashboardController::class, 'bestSalesDay']);
    });

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'destroy']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);
    Route::get('/reports/excel', [ReportController::class, 'exportExcel']);
});