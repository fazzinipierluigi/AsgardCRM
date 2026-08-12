<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityRelationTargetType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityFieldChange;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\EntityChangeLogger;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function changeLoggerEntity(): Entity
{
    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini-log', 'table_name' => 'entity_ordini_log']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $card->fields()->create(['name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select, 'options' => ['aperto' => 'Aperto', 'chiuso' => 'Chiuso'], 'position' => 1]);
    $card->fields()->create(['name' => 'Attivo', 'column_name' => 'attivo', 'type' => EntityFieldType::Checkbox, 'position' => 2]);
    $card->fields()->create([
        'name' => 'Responsabile',
        'column_name' => 'responsabile',
        'type' => EntityFieldType::Relation,
        'relation_target_type' => EntityRelationTargetType::Model,
        'relation_target' => User::class,
        'position' => 3,
    ]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('logCreated writes one row per non-null field, sharing a transaction id', function () {
    $entity = changeLoggerEntity();
    $user = User::factory()->create();
    $responsabile = User::factory()->create(['name' => 'Mario Rossi']);
    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'nome' => 'Ordine 1',
        'stato' => 'aperto',
        'attivo' => true,
        'responsabile_id' => $responsabile->id,
    ]);

    app(EntityChangeLogger::class)->logCreated($entity, $record, [
        'nome' => 'Ordine 1',
        'stato' => 'aperto',
        'attivo' => true,
        'responsabile_id' => $responsabile->id,
    ], $user);

    $changes = EntityFieldChange::where('entity_slug', 'ordini-log')->where('entity_id', $record->id)->get();

    expect($changes)->toHaveCount(4);
    expect($changes->pluck('transaction_id')->unique())->toHaveCount(1);
    expect($changes->every(fn ($c) => $c->old_value === null))->toBeTrue();
    expect($changes->firstWhere('column_name', 'stato')->new_value)->toBe('Aperto');
    expect($changes->firstWhere('column_name', 'attivo')->new_value)->toBe('Sì');
    expect($changes->firstWhere('column_name', 'responsabile')->new_value)->toBe('Mario Rossi');
    expect($changes->every(fn ($c) => $c->changed_by_user_id === $user->id))->toBeTrue();
});

test('logUpdated only writes rows for fields that actually changed', function () {
    $entity = changeLoggerEntity();
    $user = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'nome' => 'Ordine 1',
        'stato' => 'aperto',
        'attivo' => false,
    ]);

    app(EntityChangeLogger::class)->logUpdated(
        $entity,
        $record,
        ['nome' => 'Ordine 1', 'stato' => 'aperto', 'attivo' => false],
        ['nome' => 'Ordine 1', 'stato' => 'chiuso', 'attivo' => true],
        $user,
    );

    $changes = EntityFieldChange::where('entity_id', $record->id)->get();

    expect($changes)->toHaveCount(2);
    expect($changes->pluck('column_name')->sort()->values()->all())->toBe(['attivo', 'stato']);
    $statoChange = $changes->firstWhere('column_name', 'stato');
    expect($statoChange->old_value)->toBe('Aperto');
    expect($statoChange->new_value)->toBe('Chiuso');
});

test('logUpdated writes nothing when no field actually changed', function () {
    $entity = changeLoggerEntity();
    $user = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'nome' => 'Ordine 1',
        'stato' => 'aperto',
    ]);

    app(EntityChangeLogger::class)->logUpdated(
        $entity,
        $record,
        ['nome' => 'Ordine 1', 'stato' => 'aperto'],
        ['nome' => 'Ordine 1', 'stato' => 'aperto'],
        $user,
    );

    expect(EntityFieldChange::where('entity_id', $record->id)->count())->toBe(0);
});

test('a workflow-sourced change has no user but carries a source label', function () {
    $entity = changeLoggerEntity();
    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => User::factory()->create()->id,
        'nome' => 'Ordine 1',
    ]);

    app(EntityChangeLogger::class)->logCreated($entity, $record, ['nome' => 'Ordine 1'], null, 'Flusso: Chiusura automatica');

    $change = EntityFieldChange::where('entity_id', $record->id)->firstOrFail();
    expect($change->changed_by_user_id)->toBeNull();
    expect($change->changed_by_label)->toBe('Flusso: Chiusura automatica');
});
