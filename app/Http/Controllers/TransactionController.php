<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Transaction::query();

            // Apply filters
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->byDateRange($request->start_date, $request->end_date);
            }

            if ($request->has('type') && in_array($request->type, ['debit', 'credit'])) {
                $query->byType($request->type);
            }

            if ($request->has('status') && in_array($request->status, ['pending', 'completed', 'failed'])) {
                $query->byStatus($request->status);
            }

            if ($request->boolean('nfc_only')) {
                $query->withNfc();
            }

            if ($request->has('search')) {
                $query->search($request->search);
            }

            $transactions = $query->orderBy('transaction_date', 'desc')
                                ->paginate($request->get('per_page', 20));

            return response()->json($transactions);

        } catch (\Exception $e) {
            Log::error('Transaction index error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch transactions'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01|max:999999.99',
                'currency' => 'required|string|size:3',
                'type' => 'required|in:debit,credit',
                'status' => 'required|in:pending,completed,failed',
                'merchant_name' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:100',
                'nfc_data' => 'nullable|array',
                'nfc_data.card_id' => 'nullable|string|max:100',
                'nfc_data.terminal_id' => 'nullable|string|max:50',
                'nfc_data.signal_strength' => 'nullable|integer|min:-100|max:0',
                'transaction_date' => 'required|date'
            ]);

            DB::beginTransaction();

            $validated['transaction_id'] = 'TXN_' . uniqid() . '_' . time();

            $transaction = Transaction::create($validated);

            DB::commit();

            Log::info('Transaction created', ['transaction_id' => $transaction->transaction_id]);

            return response()->json($transaction, 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Transaction creation error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create transaction'], 500);
        }
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json($transaction);
    }

    public function recentNfcTransactions(): JsonResponse
    {
        try {
            $transactions = Transaction::withNfc()
                                      ->orderBy('transaction_date', 'desc')
                                      ->limit(10)
                                      ->get();

            return response()->json($transactions);
        } catch (\Exception $e) {
            Log::error('Recent NFC transactions error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch recent NFC transactions'], 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date', now()->startOfMonth());
            $endDate = $request->get('end_date', now()->endOfMonth());

            $baseQuery = Transaction::byDateRange($startDate, $endDate);

            $summary = [
                'total_transactions' => $baseQuery->count(),
                'total_amount' => round($baseQuery->sum('amount'), 2),
                'credit_amount' => round($baseQuery->byType('credit')->sum('amount'), 2),
                'debit_amount' => round($baseQuery->byType('debit')->sum('amount'), 2),
                'nfc_transactions' => $baseQuery->withNfc()->count(),
                'pending_transactions' => $baseQuery->byStatus('pending')->count(),
                'completed_transactions' => $baseQuery->byStatus('completed')->count(),
                'failed_transactions' => $baseQuery->byStatus('failed')->count(),
            ];

            return response()->json($summary);

        } catch (\Exception $e) {
            Log::error('Summary error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate summary'], 500);
        }
    }
}
