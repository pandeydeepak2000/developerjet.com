<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CatalogSeeder::class, ServicesCatalogSeeder::class, RbacSeeder::class, DocsSeeder::class, InfrastructureSeeder::class, AiAccessSeeder::class, BusinessAutomationSeeder::class]);
    }
}
