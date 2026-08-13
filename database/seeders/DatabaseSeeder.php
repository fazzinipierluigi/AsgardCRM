<?php

namespace Database\Seeders;

use Fazzinipierluigi\CrmCore\Database\Seeders\DatabaseSeeder as CrmDatabaseSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan db:seed` conventionally resolves this class by name —
     * kept as a thin host entry point delegating entirely to the
     * package's own seeder (Modulo 5), which now owns every demo/default
     * seeder this used to call directly.
     */
    public function run(): void
    {
        $this->call(CrmDatabaseSeeder::class);
    }
}
