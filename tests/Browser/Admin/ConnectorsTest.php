<?php

use Fazzinipierluigi\CrmCore\Models\Connector;
use Fazzinipierluigi\CrmCore\Models\ConnectorUserMailbox;
use Laravel\Dusk\Browser;

test('admin can create an exchange graph connector', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/connectors/create')
            ->type('name', 'Outlook Dusk')
            ->type('tenant_id', 'tenant-1')
            ->type('client_id', 'client-1')
            ->type('client_secret', 'secret-1')
            ->press('Crea connettore')
            ->waitForLocation('/admin/connectors')
            ->waitForText('Connettore creato correttamente.')
            ->assertSee('Outlook Dusk');
    });

    expect(Connector::where('name', 'Outlook Dusk')->exists())->toBeTrue();
});

test('admin can map a user mailbox for a connector', function () {
    $admin = adminUser();
    $connector = Connector::create([
        'type' => 'exchange_graph', 'name' => 'Outlook Dusk', 'slug' => 'outlook-dusk',
        'sync_direction' => 'bidirectional', 'sync_interval_minutes' => 15,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $connector) {
        $browser->loginAs($admin)
            ->visit("/admin/connectors/{$connector->id}/mailboxes")
            ->type("mailboxes[{$admin->id}]", 'admin@example.com')
            ->press('Salva modifiche')
            ->waitForText('Impostazioni aggiornate.');
    });

    expect(ConnectorUserMailbox::where('connector_id', $connector->id)->where('user_id', $admin->id)->first()?->mailbox_email)
        ->toBe('admin@example.com');
});
