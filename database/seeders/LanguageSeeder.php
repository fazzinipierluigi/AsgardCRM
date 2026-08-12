<?php

namespace Database\Seeders;

use Fazzinipierluigi\CrmCore\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    /**
     * Seed the languages available for translations/user preferences.
     * Safe to re-run: existing codes are updated in place.
     */
    public function run(): void
    {
        $languages = [
            'it' => 'Italiano',
            'en' => 'English',
        ];

        foreach ($languages as $code => $name) {
            Language::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
