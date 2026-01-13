<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'country_id', 'name'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
