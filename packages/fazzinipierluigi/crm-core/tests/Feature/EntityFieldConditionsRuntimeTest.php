<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function conditionRuntimeEntity(string $slug): Entity
{
    $entity = Entity::create(['name' => 'Clienti', 'slug' => $slug, 'table_name' => "entity_{$slug}"]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Tipo azienda', 'column_name' => 'tipo_azienda', 'type' => EntityFieldType::Checkbox, 'position' => 0]);
    $card->fields()->create(['name' => 'Partita IVA', 'column_name' => 'partita_iva', 'type' => EntityFieldType::String, 'required' => true, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('the create form embeds the entity field conditions payload', function () {
    $entity = conditionRuntimeEntity('cond-rt-create');
    $ivaField = $entity->allFields()->firstWhere('column_name', 'partita_iva');
    $condition = $entity->fieldConditions()->create(['name' => 'Nascondi P.IVA', 'rule' => ['==' => [['var' => 'tipo_azienda'], false]], 'position' => 0]);
    $condition->targets()->create(['entity_field_id' => $ivaField->id, 'visible' => false, 'readonly' => false, 'required' => false]);

    $response = $this->actingAs(adminUser())->get(route('entities.create', $entity));

    $response->assertOk()
        ->assertSee('window.ENTITY_FIELD_CONDITIONS', false)
        ->assertSee('partita_iva', false)
        ->assertSee('data-field-wrapper', false);
});

test('a field genuinely required at the entity-builder level does not block saving while an active condition hides it', function () {
    $entity = conditionRuntimeEntity('cond-rt-hidden-required');
    $ivaField = $entity->allFields()->firstWhere('column_name', 'partita_iva');
    $condition = $entity->fieldConditions()->create(['name' => 'Nascondi P.IVA', 'rule' => ['==' => [['var' => 'tipo_azienda'], '1']], 'position' => 0]);
    $condition->targets()->create(['entity_field_id' => $ivaField->id, 'visible' => false, 'readonly' => false, 'required' => false]);

    $admin = adminUser();

    // tipo_azienda checked -> the condition is active -> partita_iva is
    // hidden -> its base "required" rule must not block this submission
    // even though partita_iva is entirely absent from the payload.
    $response = $this->actingAs($admin)->post(route('entities.store', $entity), [
        'tipo_azienda' => '1',
    ]);

    $response->assertRedirect(route('entities.index', $entity));
    $response->assertSessionDoesntHaveErrors();
    expect(EntityRecord::forEntity($entity)->newQuery()->count())->toBe(1);
});

test('the same required field still blocks saving when its hiding condition is not active', function () {
    $entity = conditionRuntimeEntity('cond-rt-visible-required');
    $ivaField = $entity->allFields()->firstWhere('column_name', 'partita_iva');
    $condition = $entity->fieldConditions()->create(['name' => 'Nascondi P.IVA', 'rule' => ['==' => [['var' => 'tipo_azienda'], '1']], 'position' => 0]);
    $condition->targets()->create(['entity_field_id' => $ivaField->id, 'visible' => false, 'readonly' => false, 'required' => false]);

    $admin = adminUser();

    // tipo_azienda left unchecked -> the condition is inactive ->
    // partita_iva stays visible and its base "required" rule applies.
    $response = $this->actingAs($admin)->post(route('entities.store', $entity), [
        'tipo_azienda' => '0',
    ]);

    $response->assertSessionHasErrors('partita_iva');
    expect(EntityRecord::forEntity($entity)->newQuery()->count())->toBe(0);
});
