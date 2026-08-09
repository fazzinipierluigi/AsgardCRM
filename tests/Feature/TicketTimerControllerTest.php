<?php

use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\EntityRelation;
use App\Models\EntityRelationLink;
use App\Models\User;
use Database\Seeders\CalendarEntitySeeder;
use Database\Seeders\ClientiEntitySeeder;
use Database\Seeders\ContattiEntitySeeder;
use Database\Seeders\TicketEntitySeeder;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function seedTicketChain(): void
{
    test()->seed(CalendarEntitySeeder::class);
    test()->seed(ClientiEntitySeeder::class);
    test()->seed(ContattiEntitySeeder::class);
    test()->seed(TicketEntitySeeder::class);
}

function userWithTicketPermissions(): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);

    foreach (['index', 'create', 'edit', 'delete'] as $action) {
        $role->givePermission(Permission::where('key', "entity_ticket.{$action}")->firstOrFail());
    }

    $user->assignRole($role);

    return $user;
}

function createTicket(User $user): EntityRecord
{
    $ticketEntity = Entity::where('slug', 'ticket')->firstOrFail();

    return EntityRecord::forEntity($ticketEntity)->newQuery()->create([
        'user_id' => $user->id,
        'oggetto' => 'Non funziona il login',
        'stato' => 'aperto',
        'priorita' => 'media',
    ]);
}

test('starting the timer stamps timer_avviato_il', function () {
    seedTicketChain();
    $user = userWithTicketPermissions();
    $ticket = createTicket($user);

    Carbon::setTestNow('2026-08-01 10:00:00');

    $response = $this->actingAs($user)->postJson(route('tickets.timer.start', $ticket->id));

    $response->assertOk();
    $ticket->refresh();
    expect(Carbon::parse($ticket->timer_avviato_il)->toDateTimeString())->toBe('2026-08-01 10:00:00');

    Carbon::setTestNow();
});

test('starting an already-started timer fails', function () {
    seedTicketChain();
    $user = userWithTicketPermissions();
    $ticket = createTicket($user);

    $this->actingAs($user)->postJson(route('tickets.timer.start', $ticket->id))->assertOk();
    $response = $this->actingAs($user)->postJson(route('tickets.timer.start', $ticket->id));

    $response->assertStatus(422);
});

test('stopping without starting fails', function () {
    seedTicketChain();
    $user = userWithTicketPermissions();
    $ticket = createTicket($user);

    $response = $this->actingAs($user)->postJson(route('tickets.timer.stop', $ticket->id));

    $response->assertStatus(422);
});

test('stopping the timer accumulates elapsed minutes, clears the start stamp, and creates a linked calendar entry', function () {
    seedTicketChain();
    $user = userWithTicketPermissions();
    $ticket = createTicket($user);

    Carbon::setTestNow('2026-08-01 10:00:00');
    $this->actingAs($user)->postJson(route('tickets.timer.start', $ticket->id))->assertOk();

    Carbon::setTestNow('2026-08-01 10:25:00');
    $response = $this->actingAs($user)->postJson(route('tickets.timer.stop', $ticket->id));

    $response->assertOk();

    $ticket->refresh();
    expect($ticket->timer_avviato_il)->toBeNull();
    expect((float) $ticket->tempo_tracciato_minuti)->toBe(25.0);

    $calendarEntity = Entity::where('slug', 'calendario')->firstOrFail();
    $event = EntityRecord::forEntity($calendarEntity)->newQuery()
        ->where('relatable_type', 'entity:ticket')
        ->where('relatable_id', $ticket->id)
        ->first();

    expect($event)->not->toBeNull();
    expect(Carbon::parse($event->start_datetime)->toDateTimeString())->toBe('2026-08-01 10:00:00');
    expect(Carbon::parse($event->end_datetime)->toDateTimeString())->toBe('2026-08-01 10:25:00');

    $ticketEntity = Entity::where('slug', 'ticket')->firstOrFail();
    $relation = EntityRelation::where('entity_a_id', $ticketEntity->id)
        ->where('entity_b_id', $calendarEntity->id)
        ->firstOrFail();

    expect(EntityRelationLink::where('entity_relation_id', $relation->id)
        ->where('entity_a_record_id', $ticket->id)
        ->where('entity_b_record_id', $event->id)
        ->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('stopping the timer twice accumulates minutes across sessions', function () {
    seedTicketChain();
    $user = userWithTicketPermissions();
    $ticket = createTicket($user);

    Carbon::setTestNow('2026-08-01 09:00:00');
    $this->actingAs($user)->postJson(route('tickets.timer.start', $ticket->id))->assertOk();
    Carbon::setTestNow('2026-08-01 09:10:00');
    $this->actingAs($user)->postJson(route('tickets.timer.stop', $ticket->id))->assertOk();

    Carbon::setTestNow('2026-08-01 14:00:00');
    $this->actingAs($user)->postJson(route('tickets.timer.start', $ticket->id))->assertOk();
    Carbon::setTestNow('2026-08-01 14:20:00');
    $this->actingAs($user)->postJson(route('tickets.timer.stop', $ticket->id))->assertOk();

    $ticket->refresh();
    expect((float) $ticket->tempo_tracciato_minuti)->toBe(30.0);

    Carbon::setTestNow();
});

test('a user without ticket permission cannot use the timer', function () {
    seedTicketChain();
    $owner = userWithTicketPermissions();
    $ticket = createTicket($owner);
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->postJson(route('tickets.timer.start', $ticket->id));

    $response->assertForbidden();
});
