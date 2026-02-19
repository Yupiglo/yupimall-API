<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $fillable = [
        'sponsor_id',
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'zip_code',
        'plan',
        'payment_method',
        'payment_status', // 'pending', 'paid', 'failed'
        'status',
        'password',
        'created_by',
        'requested_role',
        'wallet_pin_id',
        'package_id',
        'package_price',
    ];

    public function walletPin()
    {
        return $this->belongsTo(WalletPin::class, 'wallet_pin_id');
    }

}
