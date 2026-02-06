<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckBrokenImages extends Command
{
    protected $signature = 'products:check-images';
    protected $description = 'Check for products with missing/broken image files';

    public function handle()
    {
        $products = Product::all();
        $broken = [];
        $valid = 0;
        $uploadsPath = public_path('uploads/products');

        $this->info("Checking {$products->count()} products...\n");

        foreach ($products as $product) {
            $imgCover = $product->img_cover;

            if (empty($imgCover)) {
                $broken[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'img_cover' => '(empty)',
                    'reason' => 'No image set'
                ];
                continue;
            }

            // Build the expected file path
            $filename = $imgCover;
            if (str_starts_with($imgCover, 'uploads/products/')) {
                $filename = str_replace('uploads/products/', '', $imgCover);
            } elseif (str_starts_with($imgCover, '/uploads/products/')) {
                $filename = str_replace('/uploads/products/', '', $imgCover);
            }

            $fullPath = $uploadsPath . '/' . $filename;

            if (!File::exists($fullPath)) {
                $broken[] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'img_cover' => $imgCover,
                    'expected_path' => $fullPath,
                    'reason' => 'File not found'
                ];
            } else {
                $valid++;
            }
        }

        $this->info("Results:");
        $this->info("- Valid images: {$valid}");
        $this->info("- Broken images: " . count($broken));
        $this->newLine();

        if (count($broken) > 0) {
            $this->warn("Products with broken images:");
            $this->table(
                ['ID', 'Title', 'img_cover', 'Reason'],
                array_map(fn($b) => [$b['id'], substr($b['title'], 0, 40), substr($b['img_cover'], 0, 40), $b['reason']], $broken)
            );
        } else {
            $this->info("All product images are valid!");
        }

        return 0;
    }
}
