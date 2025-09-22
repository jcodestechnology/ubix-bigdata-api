<?php

use Illuminate\Http\Request;
use App\Http\Controllers\TransactionExportController;
use App\Http\Controllers\TransactionUploadController;
use App\Http\Controllers\TransactionDataTableController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

// Public routes

Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,5');

// Protected routes
Route::middleware(['token.auth'])->group(function () {

     Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::get('/user', [LoginController::class, 'user']);
   // Route::post('/refresh', [LoginController::class, 'refresh']);

    Route::post('/upload-csv', [TransactionUploadController::class, 'upload']);
    
    Route::prefix('export')->group(function () {
        Route::post('/request', [TransactionExportController::class, 'requestExport']);
        Route::get('/status/{exportId}', [TransactionExportController::class, 'checkExportStatus']);
        Route::get('/download/{exportId}', [TransactionExportController::class, 'downloadExport']);
    
        Route::post('/cleanup', [TransactionExportController::class, 'cleanupOldExports']);
    });

    Route::prefix('transactions')->group(function () {
        Route::get('/datatable', [TransactionDataTableController::class, 'index']);
        Route::get('/filter-options/{column}', [TransactionDataTableController::class, 'getFilterOptions']);
        Route::get('/statistics', [TransactionDataTableController::class, 'getStatistics']);
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

});
