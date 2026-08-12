<?php

use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Database\Seeders\DocumentsEntitySeeder;
use Laravel\Dusk\Browser;

test('a user can create a folder, upload a document into it, and delete the document', function () {
    app(DocumentsEntitySeeder::class)->run(app(EntityInstaller::class));
    $admin = adminUser();

    $fixturePath = sys_get_temp_dir().'/dusk-document-'.uniqid().'.pdf';
    file_put_contents($fixturePath, '%PDF-1.4 test content');

    $this->browse(function (Browser $browser) use ($admin, $fixturePath) {
        $browser->loginAs($admin)
            ->visit('/documents')
            ->waitFor('[data-testid="document-new-folder-btn"]')
            ->click('[data-testid="document-new-folder-btn"]')
            ->waitFor('#new-folder-modal.show')
            ->type('name', 'Contratti')
            ->click('[data-testid="new-folder-submit"]')
            ->waitForLocation('/documents')
            ->waitForText('Contratti')
            ->click('[data-testid^="document-folder-tree-item-"]')
            ->waitForText('Carica documento');

        $browser->click('[data-testid="document-upload-link"]')
            ->waitForLocation('/documents/upload')
            ->attach('file', $fixturePath)
            ->type('nome', 'Contratto di prova')
            ->press('Salva')
            ->waitForText('Contratto di prova');

        $browser->assertSee('Contratto di prova')
            ->assertSee('Contratti'); // still inside the folder we uploaded into

        $browser->press('[data-testid^="document-delete-btn-"]')
            ->waitForDialog()
            ->acceptDialog()
            ->waitUntilMissingText('Contratto di prova');
    });

    @unlink($fixturePath);
});
