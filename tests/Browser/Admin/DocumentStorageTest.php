<?php

use Fazzinipierluigi\CrmCore\Models\DocumentStorageSetting;
use Laravel\Dusk\Browser;

test('admin can switch document storage to an s3 bucket and back', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/document-storage')
            ->assertVisible('#type')
            ->select('type', 's3')
            ->waitFor('[data-storage-config="s3"]')
            ->assertVisible('#key')
            ->type('key', 'AKIA123')
            ->type('secret', 'shh')
            ->type('region', 'eu-west-1')
            ->type('bucket', 'documenti-crm')
            ->press('Salva impostazioni')
            ->waitForText('Impostazioni storage aggiornate.');
    });

    $setting = DocumentStorageSetting::current();
    expect($setting->type->value)->toBe('s3');
    expect($setting->config['bucket'])->toBe('documenti-crm');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/document-storage')
            ->select('type', 'local')
            ->press('Salva impostazioni')
            ->waitForText('Impostazioni storage aggiornate.');
    });

    expect(DocumentStorageSetting::current()->type->value)->toBe('local');
});

test('the document storage settings link is visible in the admin menu', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/document-storage')
            ->assertSeeLink('Storage documenti');
    });
});
