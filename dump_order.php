<?php

use App\Models\Product;
use App\Models\Order;

define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$trackingCode = 'OC-964268'; // From the screenshot
$order = Order::where('tracking_code', $trackingCode)->with('items.product')->first();

if (!$order) {
    echo "Order not found\n";
    exit;
}

echo "Order Status: " . $order->order_status . "\n";
foreach ($order->items as $item) {
    echo "Item: " . ($item->product?->title ?? 'N/A') . "\n";
    echo "Product ID: " . ($item->product?->id ?? 'N/A') . "\n";
    echo "img_cover: " . ($item->product?->img_cover ?? 'NULL') . "\n";
    echo "image (item field if exists): " . ($item->image ?? 'N/A') . "\n";
    echo "-------------------\n";
}
