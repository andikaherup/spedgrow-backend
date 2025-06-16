<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;

Route::get('/', function () {
    return view('welcome');
});

// Add your API routes here with api prefix
Route::prefix('api/v1')->group(function () {
    Route::get('transactions/nfc/recent', [TransactionController::class, 'recentNfcTransactions']);
    Route::get('transactions/stats/summary', [TransactionController::class, 'summary']);
    Route::apiResource('transactions', TransactionController::class);
});
