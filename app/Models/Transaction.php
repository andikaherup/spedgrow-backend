<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'amount',
        'currency',
        'type',
        'status',
        'merchant_name',
        'category',
        'nfc_data',
        'transaction_date'
    ];

    protected $casts = [
        'nfc_data' => 'array',
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2'
    ];

    // Scopes for filtering
    public function scopeByDateRange(Builder $query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeByType(Builder $query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus(Builder $query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeWithNfc(Builder $query)
    {
        return $query->whereNotNull('nfc_data');
    }

    public function scopeSearch(Builder $query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('merchant_name', 'LIKE', "%{$search}%")
              ->orWhere('transaction_id', 'LIKE', "%{$search}%")
              ->orWhere('category', 'LIKE', "%{$search}%");
        });
    }
}
