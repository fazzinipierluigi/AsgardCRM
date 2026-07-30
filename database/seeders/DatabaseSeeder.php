<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's base data (languages, translations, the
     * default calendar entity). The admin role/user/login provider are
     * created by the installation wizard (App\Services\ApplicationInstaller)
     * instead of here.
     */
    public function run(): void
    {
        $this->call(LanguageSeeder::class);
        $this->call(TranslationSeeder::class);
        $this->call(CalendarEntitySeeder::class);
    }
}
