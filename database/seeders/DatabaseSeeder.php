<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@yupi.com'],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'role' => 'admin',
                'password' => Hash::make('password123'),
            ]
        );

        $this->call([
            CountrySeeder::class,
            InitialCatalogSeeder::class,
        ]);
    }
}
