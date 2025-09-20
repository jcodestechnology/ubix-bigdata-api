<?php

use Illuminate\Http\Request;
use App\Http\Controllers\TransactionExportController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

use App\Http\Controllers\TransactionUploadController;

Route::post('/upload-csv', [TransactionUploadController::class, 'upload']);
Route::get('/status', function () {
    return response()->json(['status' => 'ok'], 200);
});
Route::prefix('export')->group(function () {
    Route::post('/request', [TransactionExportController::class, 'requestExport']);
    Route::get('/status/{exportId}', [TransactionExportController::class, 'checkExportStatus']);
    Route::get('/download/{exportId}', [TransactionExportController::class, 'downloadExport']);
    Route::get('/list', [TransactionExportController::class, 'listExports']);
    Route::post('/cleanup', [TransactionExportController::class, 'cleanupOldExports']);
});