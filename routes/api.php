<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;

Route::prefix('v1')->group(function () {
    // Put specific routes FIRST (before the resource route)
    Route::get('transactions/nfc/recent', [TransactionController::class, 'recentNfcTransactions']);
    Route::get('transactions/stats/summary', [TransactionController::class, 'summary']);

    // Resource route comes LAST
    Route::apiResource('transactions', TransactionController::class);
});
