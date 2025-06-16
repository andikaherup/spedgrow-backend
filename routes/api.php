<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;

Route::prefix('v1')->middleware('cors')->group(function () {
    Route::apiResource('transactions', TransactionController::class);
    Route::get('transactions/nfc/recent', [TransactionController::class, 'recentNfcTransactions']);
    Route::get('transactions/stats/summary', [TransactionController::class, 'summary']);
});
