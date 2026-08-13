<?php

use App\Models\User;
use Fazzinipierluigi\CrmCore\Database\Seeders\CalendarEntitySeeder;
use Fazzinipierluigi\CrmCore\Models\CalendarShare;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Laravel\Dusk\Browser;

test('sharing a calendar makes its events visible to the other user', function () {
    $this->seed(CalendarEntitySeeder::class);

    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', 'entity_calendario.index')->firstOrFail());
    $owner = User::factory()->create(['name' => 'Proprietario']);
    $owner->assignRole($role);
    $viewer = User::factory()->create(['name' => 'Osservatore']);
    $viewer->assignRole($role);

    $entity = Entity::where('slug', 'calendario')->firstOrFail();
    EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $owner->id,
        'title' => 'Evento condiviso',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => now()->startOfMonth()->addDays(3)->setTime(9, 0),
        'end_datetime' => now()->startOfMonth()->addDays(3)->setTime(10, 0),
    ]);

    // Before sharing: the viewer's calendar does not show the owner's event.
    $this->browse(function (Browser $browser) use ($viewer) {
        $browser->loginAs($viewer)
            ->visit('/calendar')
            ->waitFor('.fc-toolbar')
            ->pause(500)
            ->assertDontSee('Evento condiviso');
    });

    $this->browse(function (Browser $browser) use ($owner, $viewer) {
        $browser->loginAs($owner)
            ->visit('/calendar/settings')
            ->waitForText('Condivisioni attive')
            ->radio("shares[{$viewer->id}]", 'view')
            ->press('Salva')
            ->waitForText('Impostazioni aggiornate.');
    });

    expect(CalendarShare::where('owner_user_id', $owner->id)->where('shared_with_user_id', $viewer->id)->first()?->permission->value)
        ->toBe('view');

    // After sharing: the owner's event is now visible on the viewer's calendar.
    $this->browse(function (Browser $browser) use ($viewer) {
        $browser->loginAs($viewer)
            ->visit('/calendar')
            ->waitForText('Evento condiviso');
    });
});
