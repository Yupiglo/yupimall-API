<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'tracking_code',
        'shipping_name',
        'shipping_street',
        'shipping_city',
        'shipping_country',
        'shipping_zip',
        'shipping_phone',
        'shipping_email',
        'distributor_id',
        'stockist',
        'order_status',
        'payment_method',
        'is_paid',
        'is_delivered',
        'total_order_price',
        'order_at',
        'paid_at',
        'delivered_at',
        'payment_proof',
        'delivery_person_id',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'is_delivered' => 'boolean',
        'total_order_price' => 'decimal:2',
        'order_at' => 'datetime',
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Generate a unique tracking code for guest orders
     */
    public static function generateTrackingCode(): string
    {
        do {
            $code = 'YM-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (self::where('tracking_code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_person_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
