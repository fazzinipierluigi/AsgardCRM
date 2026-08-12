<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityFieldCondition;
use Fazzinipierluigi\CrmCore\Models\EntityFieldConditionTarget;
use App\Models\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * relationTestEntity() (tests/Pest.php) gives a single "nome" String
 * field — these tests add a second field where they need more than
 * one to build a meaningful condition.
 */
function conditionTestEntity(string $slug): Entity
{
    $entity = relationTestEntity($slug, 'Condizioni');
    $card = $entity->tabs->first()->cards->first();
    $card->fields()->create(['name' => 'Attivo', 'column_name' => 'attivo', 'type' => EntityFieldType::Checkbox, 'position' => 1]);

    return $entity->fresh();
}

test('an admin can create a condition with a rule and managed field targets', function () {
    $entity = conditionTestEntity('cond-create');
    $nomeField = $entity->allFields()->firstWhere('column_name', 'nome');
    $attivoField = $entity->allFields()->firstWhere('column_name', 'attivo');

    $response = $this->actingAs(adminUser())->post(route('admin.entities.conditions.store', $entity), [
        'name' => 'Nascondi nome se non attivo',
        'rule' => json_encode(['==' => [['var' => 'attivo'], true]]),
        'fields' => [
            $nomeField->id => ['managed' => '1', 'visible' => '1', 'readonly' => '0', 'required' => '1'],
            $attivoField->id => ['managed' => '0'],
        ],
    ]);

    $response->assertRedirect(route('admin.entities.conditions.index', $entity));
    $condition = EntityFieldCondition::firstOrFail();
    expect($condition->name)->toBe('Nascondi nome se non attivo');
    expect($condition->rule)->toBe(['==' => [['var' => 'attivo'], true]]);
    expect($condition->targets)->toHaveCount(1);
    expect($condition->targets->first()->entity_field_id)->toBe($nomeField->id);
    expect($condition->targets->first()->required)->toBeTrue();
    expect($condition->targets->first()->readonly)->toBeFalse();
});

test('updating a condition fully replaces its targets', function () {
    $entity = conditionTestEntity('cond-update');
    $nomeField = $entity->allFields()->firstWhere('column_name', 'nome');
    $attivoField = $entity->allFields()->firstWhere('column_name', 'attivo');

    $condition = $entity->fieldConditions()->create(['name' => 'X', 'rule' => null, 'position' => 0]);
    $condition->targets()->create(['entity_field_id' => $nomeField->id, 'visible' => false, 'readonly' => false, 'required' => false]);

    $this->actingAs(adminUser())->put(route('admin.entities.conditions.update', [$entity, $condition]), [
        'name' => 'X aggiornata',
        'rule' => '',
        'fields' => [
            $attivoField->id => ['managed' => '1', 'visible' => '1', 'readonly' => '1', 'required' => '0'],
        ],
    ])->assertRedirect(route('admin.entities.conditions.index', $entity));

    $condition->refresh();
    expect($condition->name)->toBe('X aggiornata');
    expect($condition->rule)->toBeNull();
    expect($condition->targets)->toHaveCount(1);
    expect($condition->targets->first()->entity_field_id)->toBe($attivoField->id);
    expect($condition->targets->first()->readonly)->toBeTrue();
});

test('deleting a condition removes its targets', function () {
    $entity = conditionTestEntity('cond-delete');
    $nomeField = $entity->allFields()->firstWhere('column_name', 'nome');
    $condition = $entity->fieldConditions()->create(['name' => 'X', 'rule' => null, 'position' => 0]);
    $condition->targets()->create(['entity_field_id' => $nomeField->id]);

    $this->actingAs(adminUser())->delete(route('admin.entities.conditions.destroy', [$entity, $condition]))
        ->assertRedirect(route('admin.entities.conditions.index', $entity));

    expect(EntityFieldCondition::find($condition->id))->toBeNull();
    expect(EntityFieldConditionTarget::count())->toBe(0);
});

test('a field id from another entity is silently ignored, not persisted as a target', function () {
    $entity = conditionTestEntity('cond-cross-a');
    $other = conditionTestEntity('cond-cross-b');
    $otherField = $other->allFields()->firstWhere('column_name', 'nome');

    $this->actingAs(adminUser())->post(route('admin.entities.conditions.store', $entity), [
        'name' => 'X',
        'rule' => '',
        'fields' => [
            $otherField->id => ['managed' => '1', 'visible' => '1'],
        ],
    ]);

    $condition = EntityFieldCondition::where('entity_id', $entity->id)->firstOrFail();
    expect($condition->targets)->toHaveCount(0);
});

test('a non-admin user is forbidden from the conditions admin', function () {
    $entity = conditionTestEntity('cond-forbidden');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.entities.conditions.index', $entity))->assertForbidden();
});

test('a user with the relevant admin permission can access the conditions admin', function () {
    $entity = conditionTestEntity('cond-allowed');
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-conditions']);
    $permission = Permission::firstOrCreate(['key' => 'entityfieldcondition.index'], ['name' => 'Vedi condizioni']);
    $role->givePermission($permission);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.entities.conditions.index', $entity))->assertOk();
});
