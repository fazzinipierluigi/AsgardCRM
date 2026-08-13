<?php

namespace Fazzinipierluigi\CrmCore\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's base data (languages, translations, the
     * default calendar and documents entities). The admin role/user/login provider are
     * created by the installation wizard (Fazzinipierluigi\CrmCore\Services\ApplicationInstaller)
     * instead of here.
     */
    public function run(): void
    {
        $this->call(LanguageSeeder::class);
        $this->call(TranslationSeeder::class);
        $this->call(CalendarEntitySeeder::class);
        $this->call(DocumentsEntitySeeder::class);
        $this->call(EmailEntitySeeder::class);

        // CRM system entities — order matters: each Relation/ProductsBlock
        // field only gets a real FK / working catalog picker when its
        // target entity is already installed (see EntitySchemaBuilder),
        // so every entity here is seeded after everything it points to.
        $this->call(ProdottiEntitySeeder::class);
        $this->call(ClientiEntitySeeder::class);
        $this->call(FornitoriEntitySeeder::class);
        $this->call(ContattiEntitySeeder::class);
        $this->call(LeadEntitySeeder::class);
        $this->call(OpportunitaEntitySeeder::class);
        $this->call(PreventiviEntitySeeder::class);
        $this->call(OrdiniVenditaEntitySeeder::class);
        $this->call(OrdiniAcquistoEntitySeeder::class);
        $this->call(FattureEntitySeeder::class);
        $this->call(TicketEntitySeeder::class);
    }
}
