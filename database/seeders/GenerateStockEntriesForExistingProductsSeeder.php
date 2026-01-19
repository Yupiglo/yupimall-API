<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\StockEntry;
use Illuminate\Support\Str;

class GenerateStockEntriesForExistingProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::where('quantity', '>', 0)->get();

        foreach ($products as $product) {
            // Check if this product already has any stock entry
            if (StockEntry::where('product_id', $product->id)->exists()) {
                continue;
            }

            StockEntry::create([
                'product_id' => $product->id,
                'user_id' => 1, // Assign to admin or system
                'quantity' => $product->quantity,
                'unit_price' => $product->price,
                'notes' => 'Stock initial (Régularisation)',
                'reference' => 'REG-' . strtoupper(Str::random(6)),
                'created_at' => $product->created_at, // Use product creation date
            ]);

            $this->command->info("Created stock entry for product: {$product->title}");
        }
    }
}
