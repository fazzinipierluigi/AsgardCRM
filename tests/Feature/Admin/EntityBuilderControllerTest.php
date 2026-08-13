<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\EntityRelationTargetType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function entityWithTree(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => 'string', 'position' => 0]);

    return $entity;
}

/**
 * A real installed entity (physical table exists) — needed for the
 * post-install editing tests below, unlike entityWithTree() which is
 * only ever flagged is_installed without a table to back it.
 */
function installedEntityWithTree(): Entity
{
    $entity = entityWithTree();
    app(EntityInstaller::class)->install($entity);

    return $entity->fresh(['tabs.cards.fields']);
}

function simpleTreePayload(): array
{
    return [
        'tabs' => [
            't1' => [
                'name' => 'Generale',
                'cards' => [
                    'c1' => [
                        'name' => 'Anagrafica',
                        'fields' => [
                            'f1' => [
                                'name' => 'Nome',
                                'column_name' => 'nome',
                                'type' => 'string',
                                'required' => '1',
                                'default_value' => '',
                            ],
                            'f2' => [
                                'name' => 'Stato',
                                'column_name' => 'stato',
                                'type' => 'select',
                                'options' => "open:Aperto\nclosed:Chiuso",
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('guests are redirected to login', function () {
    $entity = entityWithTree();

    $this->get(route('admin.entities.builder.edit', $entity))->assertRedirect(route('login'));
});

test('admin can view the builder page', function () {
    $entity = entityWithTree();

    $this->actingAs(adminUser())->get(route('admin.entities.builder.edit', $entity))->assertOk();
});

test('the first tab and its first card are never removable, even before install, while a second one still is', function () {
    $entity = entityWithTree();
    $secondTab = $entity->tabs()->create(['name' => 'Extra', 'position' => 1]);
    $secondTab->cards()->create(['name' => 'Altra card', 'position' => 0]);
    $entity->load('tabs.cards');

    $response = $this->actingAs(adminUser())->get(route('admin.entities.builder.edit', $entity));

    $response->assertOk();
    $html = $response->getContent();

    $firstTabPane = Str::before(Str::after($html, 'id="tab-pane-'.$entity->tabs[0]->id.'"'), 'id="tab-pane-'.$secondTab->id.'"');
    expect($firstTabPane)->not->toContain('Rimuovi tab');
    expect($firstTabPane)->not->toContain('card-remove-btn');

    $secondTabPane = Str::after($html, 'id="tab-pane-'.$secondTab->id.'"');
    expect($secondTabPane)->toContain('Rimuovi tab');
    expect($secondTabPane)->toContain('card-remove-btn');
});

test('admin can save a tab/card/field tree', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), simpleTreePayload());

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    $entity->load('tabs.cards.fields');

    expect($entity->tabs)->toHaveCount(1);
    expect($entity->tabs->first()->cards)->toHaveCount(1);

    $fields = $entity->tabs->first()->cards->first()->fields;
    expect($fields)->toHaveCount(2);

    $nome = $fields->firstWhere('column_name', 'nome');
    expect($nome->required)->toBeTrue();

    $stato = $fields->firstWhere('column_name', 'stato');
    expect($stato->options)->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
});

test('saving a new tree replaces the previous one entirely', function () {
    $admin = adminUser();
    $entity = entityWithTree();

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            't1' => [
                'name' => 'Nuovo',
                'cards' => [
                    'c1' => [
                        'name' => 'Card',
                        'fields' => [
                            'f1' => ['name' => 'Cognome', 'column_name' => 'cognome', 'type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $entity->load('tabs.cards.fields');
    expect($entity->tabs)->toHaveCount(1);
    expect($entity->tabs->first()->name)->toBe('Nuovo');
    expect($entity->allFields()->pluck('column_name')->all())->toBe(['cognome']);
});

test('at least one tab is required', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), ['tabs' => []]);

    $response->assertSessionHasErrors('tabs');
});

test('a card must have at least one field', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields'] = [];

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields');
});

test('a reserved column name is rejected', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f1']['column_name'] = 'user_id';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f1.column_name');
});

test('column names must be unique within the entity', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['column_name'] = 'nome';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f2.column_name');
});

test('a select field requires options', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f2.options');
});

test('a relation field requires a target', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['type'] = 'relation';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f2.relation_target');
});

test('a relation field persists its target type and target', function () {
    $admin = adminUser();
    $other = Entity::create(['name' => 'Aziende', 'slug' => 'aziende', 'table_name' => 'entity_aziende']);
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['type'] = 'relation';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['relation_target'] = "entity:{$other->slug}";

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $field = $entity->allFields()->firstWhere('column_name', 'stato');
    expect($field->relation_target_type->value)->toBe('entity');
    expect($field->relation_target)->toBe('aziende');
});

test('a code field persists its prefix into options', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Fatture', 'slug' => 'fatture', 'table_name' => 'entity_fatture']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['type'] = 'code';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['code_prefix'] = 'INV-';

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $field = $entity->allFields()->firstWhere('column_name', 'stato');
    expect($field->type)->toBe(EntityFieldType::Code);
    expect($field->options)->toBe(['prefix' => 'INV-']);
});

test('a field width is persisted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f1']['width'] = '6';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['width'] = '4';

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    expect($entity->allFields()->firstWhere('column_name', 'nome')->width)->toBe(6);
    expect($entity->allFields()->firstWhere('column_name', 'stato')->width)->toBe(4);
});

test('a field width defaults to 12 when not submitted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), simpleTreePayload());

    expect($entity->allFields()->firstWhere('column_name', 'nome')->width)->toBe(12);
});

test('an out-of-range field width is rejected', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f1']['width'] = '99';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f1.width');
});

/**
 * Builds the "diff" payload updateInstalled() expects: existing tabs/
 * cards/fields keep their real ids, matching the shape the loaded page
 * would actually submit.
 */
function installedTreePayload(Entity $entity, array $fieldOverrides = []): array
{
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();
    $field = $card->fields->first();

    return [
        'tabs' => [
            $tab->id => [
                'name' => $tab->name,
                'cards' => [
                    $card->id => [
                        'name' => $card->name,
                        'fields' => [
                            $field->id => array_merge([
                                'name' => $field->name,
                                'required' => $field->required ? '1' : '0',
                                'default_value' => $field->default_value,
                                'width' => $field->width,
                            ], $fieldOverrides),
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('admin can still view the builder page for an installed entity', function () {
    $entity = installedEntityWithTree();

    $this->actingAs(adminUser())->get(route('admin.entities.builder.edit', $entity))->assertOk();
});

test('an installed entity field can have its metadata updated', function () {
    $entity = installedEntityWithTree();
    $payload = installedTreePayload($entity, ['name' => 'Nome completo', 'required' => '1', 'width' => '6']);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'nome');
    expect($field->name)->toBe('Nome completo');
    expect($field->required)->toBeTrue();
    expect($field->width)->toBe(6);
});

test('an installed entity card can be renamed', function () {
    $entity = installedEntityWithTree();
    $payload = installedTreePayload($entity);
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();
    $payload['tabs'][$tab->id]['cards'][$card->id]['name'] = 'Dati anagrafici';

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect($card->fresh()->name)->toBe('Dati anagrafici');
});

test('an installed entity tab can be renamed', function () {
    $entity = installedEntityWithTree();
    $payload = installedTreePayload($entity);
    $tab = $entity->tabs->first();
    $payload['tabs'][$tab->id]['name'] = 'Dati generali';

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect($tab->fresh()->name)->toBe('Dati generali');
});

test('an installed entity field keeps its column_name and type regardless of the submitted payload', function () {
    $entity = installedEntityWithTree();
    $payload = installedTreePayload($entity);
    $fieldId = $entity->tabs->first()->cards->first()->fields->first()->id;
    $payload['tabs'][$entity->tabs->first()->id]['cards'][$entity->tabs->first()->cards->first()->id]['fields'][$fieldId]['column_name'] = 'cognome';
    $payload['tabs'][$entity->tabs->first()->id]['cards'][$entity->tabs->first()->cards->first()->id]['fields'][$fieldId]['type'] = 'integer';

    $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $field = EntityField::find($fieldId);
    expect($field->column_name)->toBe('nome');
    expect($field->type)->toBe(EntityFieldType::String);
    expect(Schema::hasColumn('entity_contatti', 'nome'))->toBeTrue();
    expect(Schema::hasColumn('entity_contatti', 'cognome'))->toBeFalse();
});

test('an installed entity can gain a new tab and a new empty card', function () {
    $entity = installedEntityWithTree();
    $payload = installedTreePayload($entity);
    $payload['tabs']['tnew1'] = [
        'name' => 'Nuovo tab',
        'cards' => [
            'cnew1' => ['name' => 'Nuova card', 'fields' => []],
        ],
    ];

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $entity->refresh()->load('tabs.cards');
    expect($entity->tabs)->toHaveCount(2);
    $newTab = $entity->tabs->firstWhere('name', 'Nuovo tab');
    expect($newTab)->not->toBeNull();
    expect($newTab->cards->first()->name)->toBe('Nuova card');
    expect($newTab->cards->first()->fields)->toHaveCount(0);
});

test('removing an existing field from the payload drops its column and deletes the row', function () {
    $entity = installedEntityWithTree();
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            $tab->id => [
                'name' => $tab->name,
                'cards' => [
                    $card->id => ['name' => $card->name, 'fields' => []],
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect(EntityField::where('column_name', 'nome')->exists())->toBeFalse();
    expect(Schema::hasColumn('entity_contatti', 'nome'))->toBeFalse();
});

test('removing an existing relation field drops its foreign key and column', function () {
    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini-rel', 'table_name' => 'entity_ordini_rel']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create([
        'name' => 'Responsabile',
        'column_name' => 'responsabile',
        'type' => EntityFieldType::Relation,
        'relation_target_type' => EntityRelationTargetType::Model,
        'relation_target' => User::class,
        'position' => 0,
    ]);
    app(EntityInstaller::class)->install($entity);
    $entity = $entity->fresh(['tabs.cards.fields']);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            $tab->id => [
                'name' => $tab->name,
                'cards' => [
                    $card->id => ['name' => $card->name, 'fields' => []],
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect(Schema::hasColumn('entity_ordini_rel', 'responsabile_id'))->toBeFalse();
});

test('a locked field cannot be removed via the builder and nothing is persisted', function () {
    $entity = installedEntityWithTree();
    $field = $entity->allFields()->firstWhere('column_name', 'nome');
    $field->update(['is_locked' => true]);
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            $tab->id => [
                'name' => $tab->name,
                'cards' => [
                    $card->id => ['name' => $card->name, 'fields' => []],
                ],
            ],
        ],
    ]);

    $response->assertSessionHasErrors('tabs');
    expect(EntityField::where('column_name', 'nome')->exists())->toBeTrue();
    expect(Schema::hasColumn('entity_contatti', 'nome'))->toBeTrue();
});

/**
 * Adding a field to an installed entity happens through the very same
 * diff payload used for every other change here — a new field entry
 * (no `id`) alongside the card's existing ones — not a separate page.
 */
function installedPayloadWithNewField(Entity $entity, array $newField, string $newToken = 'fnew1'): array
{
    $payload = installedTreePayload($entity);
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();
    $payload['tabs'][$tab->id]['cards'][$card->id]['fields'][$newToken] = $newField;

    return $payload;
}

test('a new field can be added to an installed entity and appends a real column', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, ['name' => 'Cognome', 'column_name' => 'cognome', 'type' => 'string']);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect(Schema::hasColumn('entity_contatti', 'cognome'))->toBeTrue();

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'cognome');
    expect($field)->not->toBeNull();
    expect($field->is_locked)->toBeFalse();
});

test('a new select field on an installed entity stores its parsed options', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, [
        'name' => 'Stato', 'column_name' => 'stato', 'type' => 'select', 'options' => "open:Aperto\nclosed:Chiuso",
    ]);

    $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'stato');
    expect($field->options)->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
});

test('a new relation field on an installed entity adds an _id column', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, [
        'name' => 'Responsabile', 'column_name' => 'responsabile', 'type' => 'relation', 'relation_target' => 'model:Fazzinipierluigi\AsgardCRM\\Models\\User',
    ]);

    $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    expect(Schema::hasColumn('entity_contatti', 'responsabile_id'))->toBeTrue();
});

test('a new field on an installed entity rejects a reserved column name', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, ['name' => 'Utente', 'column_name' => 'user_id', 'type' => 'string']);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.'.$entity->tabs->first()->id.'.cards.'.$entity->tabs->first()->cards->first()->id.'.fields.fnew1.column_name');
});

test('a new field on an installed entity rejects a column name already used by an existing field', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, ['name' => 'Nome duplicato', 'column_name' => 'nome', 'type' => 'string']);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.'.$entity->tabs->first()->id.'.cards.'.$entity->tabs->first()->cards->first()->id.'.fields.fnew1.column_name');
});

test('a new select field on an installed entity requires options', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, ['name' => 'Stato', 'column_name' => 'stato', 'type' => 'select']);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.'.$entity->tabs->first()->id.'.cards.'.$entity->tabs->first()->cards->first()->id.'.fields.fnew1.options');
});

test('a new button field on an installed entity stores its config and adds no column', function () {
    $entity = installedEntityWithTree();
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->start()->create();

    $payload = installedPayloadWithNewField($entity, [
        'name' => 'Avvia flusso', 'column_name' => 'avvia_flusso', 'type' => 'button',
        'button_action' => 'workflow', 'button_workflow_id' => $workflow->id,
    ]);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'avvia_flusso');
    expect($field->options)->toBe([
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
        'button_importer_ids' => [],
        'button_javascript' => null,
    ]);
    expect(Schema::hasColumn('entity_contatti', 'avvia_flusso'))->toBeFalse();
});

test('a new button field on an installed entity requires the config for its chosen action', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, [
        'name' => 'Avvia flusso', 'column_name' => 'avvia_flusso', 'type' => 'button', 'button_action' => 'workflow',
    ]);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.'.$entity->tabs->first()->id.'.cards.'.$entity->tabs->first()->cards->first()->id.'.fields.fnew1.button_workflow_id');
});

test('a new table field on an installed entity stores its parsed columns and adds a json column', function () {
    $entity = installedEntityWithTree();
    $payload = installedPayloadWithNewField($entity, [
        'name' => 'Righe ordine', 'column_name' => 'righe_ordine', 'type' => 'table',
        'table_columns' => "quantita:Quantità:integer:si\nnote:Note:string:no",
    ]);

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'righe_ordine');
    expect($field->options)->toBe([
        'columns' => [
            ['name' => 'quantita', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
            ['name' => 'note', 'label' => 'Note', 'type' => 'string', 'required' => false],
        ],
    ]);
    expect(Schema::hasColumn('entity_contatti', 'righe_ordine'))->toBeTrue();
});

test('an existing field can be moved into a newly added card', function () {
    $entity = installedEntityWithTree();
    $tab = $entity->tabs->first();
    $card = $tab->cards->first();
    $field = $card->fields->first();

    $response = $this->actingAs(adminUser())->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            $tab->id => [
                'name' => $tab->name,
                'cards' => [
                    $card->id => ['name' => $card->name, 'fields' => []],
                    'cnew1' => [
                        'name' => 'Nuova card',
                        'fields' => [
                            $field->id => ['name' => $field->name, 'width' => 12],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field->refresh();
    $newCard = EntityCard::where('name', 'Nuova card')->firstOrFail();
    expect($field->entity_card_id)->toBe($newCard->id);
});
