<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:purge-catalog {--with-orders : Also purge orders/order_items} {--with-users-data : Also purge carts, wishlists, reviews, addresses}', function () {
    $withOrders = (bool) $this->option('with-orders');
    $withUsersData = (bool) $this->option('with-users-data');

    $tables = [
        'banners',
        'posts',
        'coupons',
        'products',
        'subcategories',
        'categories',
        'brands',
    ];

    if ($withUsersData) {
        $tables = array_merge([
            'wishlist_items',
            'wishlists',
            'cart_items',
            'carts',
            'reviews',
            'addresses',
        ], $tables);
    }

    if ($withOrders) {
        $tables = array_merge([
            'order_items',
            'orders',
        ], $tables);
    }

    $tables = array_values(array_unique($tables));

    $this->info('This will DELETE data from the following tables (users will be preserved):');
    foreach ($tables as $t) {
        $this->line('- ' . $t);
    }

    if (!$this->confirm('Continue?')) {
        return;
    }

    Schema::disableForeignKeyConstraints();
    try {
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    } finally {
        Schema::enableForeignKeyConstraints();
    }

    $this->info('Purge complete.');
})->purpose('Purge demo/catalog data while keeping users/admin');

Schedule::command('wallet:expire-pins')->everyMinute();
