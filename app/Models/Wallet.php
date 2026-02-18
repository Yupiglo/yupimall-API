<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'balance',
        'currency',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(WalletPin::class, 'seller_wallet_id');
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Atomically debit the wallet with row-level locking.
     * Must be called inside a DB::transaction or will create one.
     */
    public function debit(float $amount, string $referenceType, ?int $referenceId = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $referenceType, $referenceId, $description) {
            $locked = static::lockForUpdate()->find($this->id);

            if ($locked->balance < $amount) {
                throw new \RuntimeException('Solde insuffisant.');
            }

            $balanceBefore = $locked->balance;
            $locked->decrement('balance', $amount);
            $locked->refresh();

            $this->balance = $locked->balance;

            return $this->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $balanceBefore,
                'balance_after' => $locked->balance,
            ]);
        });
    }

    /**
     * Atomically credit the wallet with row-level locking.
     */
    public function credit(float $amount, string $referenceType, ?int $referenceId = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $referenceType, $referenceId, $description) {
            $locked = static::lockForUpdate()->find($this->id);

            $balanceBefore = $locked->balance;
            $locked->increment('balance', $amount);
            $locked->refresh();

            $this->balance = $locked->balance;

            return $this->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $balanceBefore,
                'balance_after' => $locked->balance,
            ]);
        });
    }

    /**
     * Atomically refund to the wallet with row-level locking.
     */
    public function refund(float $amount, string $referenceType, ?int $referenceId = null, ?string $description = null): WalletTransaction
    {
        return DB::transaction(function () use ($amount, $referenceType, $referenceId, $description) {
            $locked = static::lockForUpdate()->find($this->id);

            $balanceBefore = $locked->balance;
            $locked->increment('balance', $amount);
            $locked->refresh();

            $this->balance = $locked->balance;

            return $this->transactions()->create([
                'type' => 'refund',
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_before' => $balanceBefore,
                'balance_after' => $locked->balance,
            ]);
        });
    }
}
