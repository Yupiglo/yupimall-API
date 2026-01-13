<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Bénin', 'iso_code' => 'BJ', 'currency' => 'XOF', 'timezone' => 'Africa/Porto-Novo'],
            ['name' => 'Togo', 'iso_code' => 'TG', 'currency' => 'XOF', 'timezone' => 'Africa/Lome'],
            ['name' => 'Nigeria', 'iso_code' => 'NG', 'currency' => 'NGN', 'timezone' => 'Africa/Lagos'],
            ['name' => 'Ghana', 'iso_code' => 'GH', 'currency' => 'GHS', 'timezone' => 'Africa/Accra'],
            ['name' => 'Burkina Faso', 'iso_code' => 'BF', 'currency' => 'XOF', 'timezone' => 'Africa/Ouagadougou'],
            ['name' => 'Niger', 'iso_code' => 'NE', 'currency' => 'XOF', 'timezone' => 'Africa/Niamey'],
            ['name' => 'Mali', 'iso_code' => 'ML', 'currency' => 'XOF', 'timezone' => 'Africa/Bamako'],
            ['name' => "Côte d'Ivoire", 'iso_code' => 'CI', 'currency' => 'XOF', 'timezone' => 'Africa/Abidjan'],
            ['name' => 'Sénégal', 'iso_code' => 'SN', 'currency' => 'XOF', 'timezone' => 'Africa/Dakar'],
            ['name' => 'Cameroun', 'iso_code' => 'CM', 'currency' => 'XAF', 'timezone' => 'Africa/Douala'],
            ['name' => 'Gabon', 'iso_code' => 'GA', 'currency' => 'XAF', 'timezone' => 'Africa/Libreville'],
            ['name' => 'Congo', 'iso_code' => 'CG', 'currency' => 'XAF', 'timezone' => 'Africa/Brazzaville'],
            ['name' => 'RD Congo', 'iso_code' => 'CD', 'currency' => 'CDF', 'timezone' => 'Africa/Kinshasa'],
            ['name' => 'Guinée', 'iso_code' => 'GN', 'currency' => 'GNF', 'timezone' => 'Africa/Conakry'],
            ['name' => 'Guinée-Bissau', 'iso_code' => 'GW', 'currency' => 'XOF', 'timezone' => 'Africa/Bissau'],
            ['name' => 'Libéria', 'iso_code' => 'LR', 'currency' => 'LRD', 'timezone' => 'Africa/Monrovia'],
            ['name' => 'Sierra Leone', 'iso_code' => 'SL', 'currency' => 'SLE', 'timezone' => 'Africa/Freetown'],
            ['name' => 'Mauritanie', 'iso_code' => 'MR', 'currency' => 'MRU', 'timezone' => 'Africa/Nouakchott'],
            ['name' => 'Gambie', 'iso_code' => 'GM', 'currency' => 'GMD', 'timezone' => 'Africa/Banjul'],
            ['name' => 'Tchad', 'iso_code' => 'TD', 'currency' => 'XAF', 'timezone' => 'Africa/Ndjamena'],
            ['name' => 'Centrafrique', 'iso_code' => 'CF', 'currency' => 'XAF', 'timezone' => 'Africa/Bangui'],
            ['name' => 'Guinée Équatoriale', 'iso_code' => 'GQ', 'currency' => 'XAF', 'timezone' => 'Africa/Malabo'],
            ['name' => 'Angola', 'iso_code' => 'AO', 'currency' => 'AOA', 'timezone' => 'Africa/Luanda'],
            ['name' => 'Rwanda', 'iso_code' => 'RW', 'currency' => 'RWF', 'timezone' => 'Africa/Kigali'],
            ['name' => 'Burundi', 'iso_code' => 'BI', 'currency' => 'BIF', 'timezone' => 'Africa/Bujumbura'],
            ['name' => 'Kenya', 'iso_code' => 'KE', 'currency' => 'KES', 'timezone' => 'Africa/Nairobi'],
            ['name' => 'Ouganda', 'iso_code' => 'UG', 'currency' => 'UGX', 'timezone' => 'Africa/Kampala'],
            ['name' => 'Tanzanie', 'iso_code' => 'TZ', 'currency' => 'TZS', 'timezone' => 'Africa/Dar_es_Salaam'],
            ['name' => 'Éthiopie', 'iso_code' => 'ET', 'currency' => 'ETB', 'timezone' => 'Africa/Addis_Ababa'],
            ['name' => 'Djibouti', 'iso_code' => 'DJ', 'currency' => 'DJF', 'timezone' => 'Africa/Djibouti'],
            ['name' => 'Somalie', 'iso_code' => 'SO', 'currency' => 'SOS', 'timezone' => 'Africa/Mogadishu'],
            ['name' => 'Maroc', 'iso_code' => 'MA', 'currency' => 'MAD', 'timezone' => 'Africa/Casablanca'],
            ['name' => 'Algérie', 'iso_code' => 'DZ', 'currency' => 'DZD', 'timezone' => 'Africa/Algiers'],
            ['name' => 'Tunisie', 'iso_code' => 'TN', 'currency' => 'TND', 'timezone' => 'Africa/Tunis'],
            ['name' => 'Égypte', 'iso_code' => 'EG', 'currency' => 'EGP', 'timezone' => 'Africa/Cairo'],
            ['name' => 'Afrique du Sud', 'iso_code' => 'ZA', 'currency' => 'ZAR', 'timezone' => 'Africa/Johannesburg'],
            ['name' => 'France', 'iso_code' => 'FR', 'currency' => 'EUR', 'timezone' => 'Europe/Paris'],
            ['name' => 'Belgique', 'iso_code' => 'BE', 'currency' => 'EUR', 'timezone' => 'Europe/Brussels'],
            ['name' => 'Suisse', 'iso_code' => 'CH', 'currency' => 'CHF', 'timezone' => 'Europe/Zurich'],
            ['name' => 'Canada', 'iso_code' => 'CA', 'currency' => 'CAD', 'timezone' => 'America/Toronto'],
            ['name' => 'USA', 'iso_code' => 'US', 'currency' => 'USD', 'timezone' => 'America/New_York'],
            ['name' => 'Chine', 'iso_code' => 'CN', 'currency' => 'CNY', 'timezone' => 'Asia/Shanghai'],
            ['name' => 'Inde', 'iso_code' => 'IN', 'currency' => 'INR', 'timezone' => 'Asia/Kolkata'],
            ['name' => 'Dubaï (UAE)', 'iso_code' => 'AE', 'currency' => 'AED', 'timezone' => 'Asia/Dubai'],
            ['name' => 'Turquie', 'iso_code' => 'TR', 'currency' => 'TRY', 'timezone' => 'Europe/Istanbul'],
        ];

        foreach ($countries as $country) {
            \App\Models\Country::updateOrCreate(
                ['iso_code' => $country['iso_code']],
                [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => $country['name'],
                    'currency' => $country['currency'],
                    'timezone' => $country['timezone']
                ]
            );
        }
    }
}
