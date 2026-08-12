<?php

use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Database\Seeders\CalendarEntitySeeder;
use Laravel\Dusk\Browser;

test('a user can create, edit, and delete a calendar event', function () {
    $this->seed(CalendarEntitySeeder::class);
    $admin = adminUser();

    // Kept inside the currently-displayed month so the created event is
    // actually visible on the grid without navigating it — FullCalendar
    // only ever fetches/renders events for the visible range.
    $start = now()->startOfMonth()->addDays(2)->setTime(10, 0)->format('Y-m-d\TH:i');
    $end = now()->startOfMonth()->addDays(2)->setTime(11, 0)->format('Y-m-d\TH:i');

    $this->browse(function (Browser $browser) use ($admin, $start, $end) {
        $browser->loginAs($admin)
            ->visit('/calendar')
            ->waitFor('.fc-toolbar')
            ->press('Nuovo evento')
            ->waitFor('#calendar-event-modal.show')
            ->type('#calendar-event-title', 'Riunione Dusk')
            ->script([
                "document.getElementById('calendar-event-start').value = '{$start}'; document.getElementById('calendar-event-start').dispatchEvent(new Event('input'));",
                "document.getElementById('calendar-event-end').value = '{$end}'; document.getElementById('calendar-event-end').dispatchEvent(new Event('input'));",
            ]);

        $browser->press('Salva')
            ->waitUntilMissing('#calendar-event-modal.show')
            ->waitForText('Riunione Dusk');
    });

    $record = EntityRecord::forEntity(Entity::where('slug', 'calendario')->first())->newQuery()->where('title', 'Riunione Dusk')->first();
    expect($record)->not->toBeNull();

    $this->browse(function (Browser $browser) {
        $browser->waitForText('Riunione Dusk')
            ->click('.fc-event')
            ->waitFor('#calendar-event-modal.show')
            ->assertInputValue('#calendar-event-title', 'Riunione Dusk')
            ->assertVisible('#calendar-event-delete-btn');

        $browser->script("document.getElementById('calendar-event-delete-btn').click();");
        $browser->waitUntilMissing('#calendar-event-modal.show')
            ->pause(500);
    });

    expect($record->fresh())->toBeNull();
});
