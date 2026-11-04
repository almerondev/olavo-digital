<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AccessSeeder::class, SettingsSeeder::class, CatalogSeeder::class]);

        if (! app()->isProduction() && env('SEED_DEMO_PASSWORD')) {
            $this->call(DemoSeeder::class);
        }
    }
}
