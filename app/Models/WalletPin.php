<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class WalletPin extends Model
{
    protected $fillable = [
        'code',
        'seller_wallet_id',
        'seller_id',
        'amount',
        'amount_used',
        'status',
        'used_by_order_id',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_used' => 'decimal:2',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    // --- Relationships ---

    public function sellerWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'seller_wallet_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'used_by_order_id');
    }

    // --- Scopes ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('expires_at', '>', Carbon::now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('expires_at', '<=', Carbon::now());
    }

    // --- Methods ---

    public function isValid(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markAsUsed(int $orderId, float $amountUsed): void
    {
        $this->update([
            'status' => 'used',
            'used_by_order_id' => $orderId,
            'amount_used' => $amountUsed,
            'used_at' => Carbon::now(),
        ]);
    }

    public function getRemainder(): float
    {
        return (float) $this->amount - (float) $this->amount_used;
    }
}
