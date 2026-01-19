<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class UpdateProductPricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Rate: 1 USD = 700 XOF
        $rate = 700;

        $products = [
            // Name (partial match) => Price in XOF
            'Alka Plus' => 16250,
            'Immuno Boost 30' => 9375,
            'Immuno Boost 60' => 17500,
            'Detox Health 30' => 9375,
            'Detox Health 60' => 17500,
            'Sea Buckthorn Juice' => 16875,
            'Dental Drop' => 2500,
            'Men Power Malt' => 18750,
            'Men Power Oil' => 6250,
            'Women Care' => 15750,
            'Diabo Care' => 15750, // Matches Diabo Care 60 Capsules
            'Diabo Care Spray' => 6250,
            'Pilon Care' => 15750,
            'Costi Away' => 13125,
            'Pain and Cold Balm' => 2500,
            'Golden Pain Oil' => 6250,
            'Yupi Drink' => 13125,
        ];

        foreach ($products as $name => $priceXof) {
            $product = Product::where('title', 'like', "%{$name}%")->first();

            if ($product) {
                $priceUsd = $priceXof / $rate;
                $product->price = round($priceUsd, 2);
                $product->save();
                $this->command->info("Updated {$product->title}: {$priceXof} XOF -> {$priceUsd} USD");
            } else {
                $this->command->warn("Product not found: {$name}");
            }
        }
    }
}
