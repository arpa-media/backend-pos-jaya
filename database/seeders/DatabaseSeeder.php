<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Database\Seeders\AuthSeeder;
use Database\Seeders\RealCatalogSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\TaxSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\OutletPivotBackfillSeeder;
use Database\Seeders\DiscountSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AuthSeeder::class,
            DemoUserSeeder::class,
            RealCatalogSeeder::class,
            PaymentMethodSeeder::class,
            OutletPivotBackfillSeeder::class,
            TaxSeeder::class,
            DiscountSeeder::class,
        ]);
    }
}
