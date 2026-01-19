<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class UpdateProductPvSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            'Alka Plus Drop' => 10,
            'Immuno Boost 30ml' => 5,
            'Immuno Boost 30' => 5, // Variant naming
            'Immuno Boost 60ml' => 10,
            'Immuno Boost 60' => 10,
            'ST Care 30ml' => 6,
            'ST Care 30' => 6,
            'ST Care 60ml' => 10,
            'ST Care 60' => 10,
            'Slim Tea' => 5,
            'Yupi Mos' => 1.5,
            'Yupi Tasty' => 1.5,
            'Yupi Fresh' => 2.5,
            'Yupi Smile' => 3,
            'Yupi Power' => 4,
            'Yupi Bright' => 3,
        ];

        foreach ($products as $name => $pv) {
            $product = Product::where('title', 'like', "%{$name}%")->first();

            if ($product) {
                $product->pv = $pv;
                $product->save();
                $this->command->info("Updated {$product->title} with PV: {$pv}");
            } else {
                $this->command->warn("Product not found: {$name}");
            }
        }
    }
}
