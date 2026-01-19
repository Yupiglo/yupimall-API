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
        'status',
    ];

}
