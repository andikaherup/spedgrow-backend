<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illumina\Http\JsonResponse;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::query();

        // Apply filters
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->boolean('nfc_only')) {
            $query->withNfc();
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
                            ->paginate($request->get('per_page', 20));

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'type' => 'required|in:debit,credit',
            'status' => 'required|in:pending,completed,failed',
            'merchant_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:100',
            'nfc_data' => 'nullable|array',
            'transaction_date' => 'required|date'
        ]);

        $validated['transaction_id'] = 'TXN_' . uniqid() . '_' . time();

        $transaction = Transaction::create($validated);

        return response()->json($transaction, 201);
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json($transaction);
    }
}
