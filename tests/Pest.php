<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\Language;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowVersion;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\AsgardCRM\Tests\TestCase;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Browser/Dusk tests are ported (namespace-rewritten) but not runnable
// yet: they need a real browser plus compiled front-end assets, and the
// package has no asset pipeline of its own (Fase 1 punto 8, not
// implemented). Skipped until that lands — see
// dev-notes/package-conversion/03-migrazione-moduli.md.
pest()->extend(TestCase::class)
    ->beforeEach(fn () => test()->markTestSkipped('Modulo 1: nessuna pipeline asset nel package ancora (Fase 1 punto 8)'))
    ->in('Browser');

/**
 * Create a user with the (system) admin role, which bypasses every
 * Just A Gate permission check. Ported from the host app's tests/Pest.php.
 */
function adminUser(): User
{
    $role = Role::firstOrCreate(
        ['slug' => 'admin'],
        ['name' => 'Administrator', 'is_admin' => true, 'is_system' => true]
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Seeds the two languages most tests expect to exist. Ported from the
 * host app's tests/Pest.php.
 */
function seedLanguages(): void
{
    Language::firstOrCreate(['code' => 'it'], ['name' => 'Italiano']);
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English']);
}

/**
 * A minimal installed entity with a single String field "nome" —
 * shared by the EntityRelation and EntityFieldCondition tests, which
 * mostly just need two or three interchangeable installed entities to
 * relate or configure, not a specific field layout. Ported from the
 * host app's tests/Pest.php.
 */
function relationTestEntity(string $slug, string $name = 'Entità'): Entity
{
    $entity = Entity::create(['name' => $name, 'slug' => $slug, 'table_name' => "entity_{$slug}"]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

/**
 * Creates a Workflow with an empty, published WorkflowVersion #1 as
 * its current_version_id — the state every real workflow is in after
 * its first builder save. Tests build the version's nodes/edges via
 * `WorkflowNode::factory()->for($workflow->currentVersion)`. Ported
 * from the host app's tests/Pest.php.
 *
 * @param  array<string, mixed>  $attributes
 */
function wfWorkflowWithVersion(array $attributes = []): Workflow
{
    $workflow = Workflow::factory()->create($attributes);
    $version = WorkflowVersion::factory()->for($workflow)->create(['version' => 1]);
    $workflow->update(['current_version_id' => $version->id]);

    return $workflow->fresh();
}
