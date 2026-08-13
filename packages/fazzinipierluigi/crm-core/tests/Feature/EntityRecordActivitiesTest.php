<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the "Attività" tab on a record's show/edit page: the reverse
 * direction of the calendar's own "Relazione verso" picker (see
 * CalendarController::relatables()), which links a calendar event to
 * any entity's record via the generic relatable_type/relatable_id pair.
 *
 * Built here as minimal, package-local fixture entities rather than
 * reusing the AsgardCRM app's own demo seeders (ClientiEntitySeeder/
 * CalendarEntitySeeder, host-owned domain data) — this suite must run
 * standalone under Testbench, with no dependency on the consuming app.
 * The slugs 'clienti'/'calendario' are load-bearing: EntityRecordController
 * hardcodes 'calendario' as the activities-source entity slug.
 */
function seedClientiAndCalendar(): void
{
    $clienti = Entity::create(['name' => 'Clienti', 'slug' => 'clienti', 'table_name' => 'entity_clienti']);
    $clientiTab = EntityTab::create(['entity_id' => $clienti->id, 'name' => 'Generale', 'position' => 0]);
    $clientiCard = EntityCard::create(['entity_tab_id' => $clientiTab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $clientiCard->fields()->create(['name' => 'Ragione sociale', 'column_name' => 'ragione_sociale', 'type' => EntityFieldType::String, 'position' => 0]);
    app(EntityInstaller::class)->install($clienti);

    $calendario = Entity::create(['name' => 'Calendario', 'slug' => 'calendario', 'table_name' => 'entity_calendario', 'is_calendar' => true]);
    $calendarioTab = EntityTab::create(['entity_id' => $calendario->id, 'name' => 'Generale', 'position' => 0]);
    $calendarioCard = EntityCard::create(['entity_tab_id' => $calendarioTab->id, 'name' => 'Evento', 'position' => 0]);
    $calendarioCard->fields()->create(['name' => 'Titolo', 'column_name' => 'title', 'type' => EntityFieldType::String, 'position' => 0]);
    $calendarioCard->fields()->create(['name' => 'Mostra come', 'column_name' => 'show_as', 'type' => EntityFieldType::String, 'position' => 1]);
    $calendarioCard->fields()->create(['name' => 'Stato', 'column_name' => 'status', 'type' => EntityFieldType::String, 'position' => 2]);
    $calendarioCard->fields()->create(['name' => 'Inizio', 'column_name' => 'start_datetime', 'type' => EntityFieldType::DateTime, 'position' => 3]);
    $calendarioCard->fields()->create(['name' => 'Fine', 'column_name' => 'end_datetime', 'type' => EntityFieldType::DateTime, 'position' => 4]);
    app(EntityInstaller::class)->install($calendario);
}

function userWithClientiAndCalendarPermissions(array $calendarActions = ['index']): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);

    $role->givePermission(Permission::where('key', 'entity_clienti.index')->firstOrFail());

    foreach ($calendarActions as $action) {
        $role->givePermission(Permission::where('key', "entity_calendario.{$action}")->firstOrFail());
    }

    $user->assignRole($role);

    return $user;
}

function createCalendarActivity(Entity $calendarEntity, User $owner, string $title, string $relatableType, int $relatableId): EntityRecord
{
    return EntityRecord::forEntity($calendarEntity)->newQuery()->create([
        'user_id' => $owner->id,
        'title' => $title,
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
        'relatable_type' => $relatableType,
        'relatable_id' => $relatableId,
    ]);
}

test('a Cliente record shows calendar activities linked to it', function () {
    seedClientiAndCalendar();
    $user = userWithClientiAndCalendarPermissions();
    $clientiEntity = Entity::where('slug', 'clienti')->firstOrFail();
    $calendarEntity = Entity::where('slug', 'calendario')->firstOrFail();

    $cliente = EntityRecord::forEntity($clientiEntity)->newQuery()->create([
        'user_id' => $user->id,
        'ragione_sociale' => 'Rossi Srl',
    ]);

    createCalendarActivity($calendarEntity, $user, 'Chiamata di follow-up', 'entity:clienti', $cliente->id);

    $response = $this->actingAs($user)->get(route('entities.show', [$clientiEntity, $cliente]));

    $response->assertOk()
        ->assertSee('data-testid="entity-record-activities-tab"', false)
        ->assertSee('Chiamata di follow-up');
});

test('the Attività tab shows an empty state when no activity is linked', function () {
    seedClientiAndCalendar();
    $user = userWithClientiAndCalendarPermissions();
    $clientiEntity = Entity::where('slug', 'clienti')->firstOrFail();

    $cliente = EntityRecord::forEntity($clientiEntity)->newQuery()->create([
        'user_id' => $user->id,
        'ragione_sociale' => 'Bianchi Srl',
    ]);

    $response = $this->actingAs($user)->get(route('entities.show', [$clientiEntity, $cliente]));

    $response->assertOk()->assertSee('data-testid="entity-activities-empty"', false);
});

test('the Attività tab is hidden from a user without calendar permission', function () {
    seedClientiAndCalendar();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);
    $role->givePermission(Permission::where('key', 'entity_clienti.index')->firstOrFail());
    $user->assignRole($role);

    $clientiEntity = Entity::where('slug', 'clienti')->firstOrFail();
    $cliente = EntityRecord::forEntity($clientiEntity)->newQuery()->create([
        'user_id' => $user->id,
        'ragione_sociale' => 'Verdi Srl',
    ]);

    $response = $this->actingAs($user)->get(route('entities.show', [$clientiEntity, $cliente]));

    $response->assertOk()->assertDontSee('data-testid="entity-record-activities-tab"', false);
});

test('a user with OwnOnly calendar visibility does not see another user\'s linked activity', function () {
    seedClientiAndCalendar();
    $viewer = userWithClientiAndCalendarPermissions();
    $otherOwner = User::factory()->create();

    $clientiEntity = Entity::where('slug', 'clienti')->firstOrFail();
    $calendarEntity = Entity::where('slug', 'calendario')->firstOrFail();

    $cliente = EntityRecord::forEntity($clientiEntity)->newQuery()->create([
        'user_id' => $viewer->id,
        'ragione_sociale' => 'Neri Srl',
    ]);

    createCalendarActivity($calendarEntity, $otherOwner, 'Riunione riservata', 'entity:clienti', $cliente->id);

    $response = $this->actingAs($viewer)->get(route('entities.show', [$clientiEntity, $cliente]));

    $response->assertOk()
        ->assertDontSee('Riunione riservata')
        ->assertSee('data-testid="entity-activities-empty"', false);
});
