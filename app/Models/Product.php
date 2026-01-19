<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'brand',
        'category',
        'subcategory',
        'category_id',
        'subcategory_id',
        'price',
        'is_new',
        'is_sale',
        'discount',
        'img_cover',
        'variants',
        'images',
        'quantity',
        'sold',
        'countries',
        'benefits',
        'ingredients',
        'how_to_use',
        'clinical_research',
        'reviews',
        'pv',
        'discount_percentage',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'pv' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'is_new' => 'boolean',
        'is_sale' => 'boolean',
        'variants' => 'array',
        'images' => 'array',
        'quantity' => 'integer',
        'sold' => 'integer',
        'countries' => 'array',
        'benefits' => 'array',
        'ingredients' => 'array',
        'reviews' => 'array',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
