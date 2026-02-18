<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'set_by',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function getRate(string $fromCurrency, string $toCurrency = 'USD'): ?float
    {
        $rate = static::active()
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->latest()
            ->first();

        return $rate?->rate ? (float) $rate->rate : null;
    }
}
