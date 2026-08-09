<?php

use App\Models\Entity;
use App\Models\EntityFieldCondition;
use App\Models\EntityRelation;
use Database\Seeders\CalendarEntitySeeder;
use Database\Seeders\ClientiEntitySeeder;
use Database\Seeders\ContattiEntitySeeder;
use Database\Seeders\DocumentsEntitySeeder;
use Database\Seeders\FattureEntitySeeder;
use Database\Seeders\FornitoriEntitySeeder;
use Database\Seeders\LeadEntitySeeder;
use Database\Seeders\OpportunitaEntitySeeder;
use Database\Seeders\OrdiniAcquistoEntitySeeder;
use Database\Seeders\OrdiniVenditaEntitySeeder;
use Database\Seeders\PreventiviEntitySeeder;
use Database\Seeders\ProdottiEntitySeeder;
use Database\Seeders\TicketEntitySeeder;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Seeds the full CRM entity chain in dependency order, exactly as
 * DatabaseSeeder does — every test below relies on this having run
 * first so Relation/ProductsBlock fields resolve their targets.
 */
function seedCrmEntities(): void
{
    test()->seed(CalendarEntitySeeder::class);
    test()->seed(DocumentsEntitySeeder::class);
    test()->seed(ProdottiEntitySeeder::class);
    test()->seed(ClientiEntitySeeder::class);
    test()->seed(FornitoriEntitySeeder::class);
    test()->seed(ContattiEntitySeeder::class);
    test()->seed(LeadEntitySeeder::class);
    test()->seed(OpportunitaEntitySeeder::class);
    test()->seed(PreventiviEntitySeeder::class);
    test()->seed(OrdiniVenditaEntitySeeder::class);
    test()->seed(OrdiniAcquistoEntitySeeder::class);
    test()->seed(FattureEntitySeeder::class);
    test()->seed(TicketEntitySeeder::class);
}

test('all 11 CRM entities install with a real table and standard CRUD permissions', function () {
    seedCrmEntities();

    $slugs = ['prodotti', 'clienti', 'fornitori', 'contatti', 'lead', 'opportunita', 'preventivi', 'ordini_vendita', 'ordini_acquisto', 'fatture', 'ticket'];

    foreach ($slugs as $slug) {
        $entity = Entity::where('slug', $slug)->first();

        expect($entity)->not->toBeNull("Entity [{$slug}] was not seeded.");
        expect($entity->is_system)->toBeTrue();
        expect($entity->is_installed)->toBeTrue();
        expect(Schema::hasTable($entity->table_name))->toBeTrue();

        foreach (['index', 'create', 'edit', 'delete'] as $action) {
            expect(Permission::where('key', "entity_{$slug}.{$action}")->exists())->toBeTrue("Missing permission entity_{$slug}.{$action}");
        }
    }
});

test('running every CRM seeder twice does not duplicate any entity', function () {
    seedCrmEntities();
    seedCrmEntities();

    foreach (['prodotti', 'clienti', 'fornitori', 'contatti', 'lead', 'opportunita', 'preventivi', 'ordini_vendita', 'ordini_acquisto', 'fatture', 'ticket'] as $slug) {
        expect(Entity::where('slug', $slug)->count())->toBe(1);
    }
});

test('Contatti has real foreign keys pointing at Clienti and Fornitori', function () {
    seedCrmEntities();

    expect(Schema::hasColumns('entity_contatti', ['cliente_collegato_id', 'fornitore_collegato_id']))->toBeTrue();
});

test('Preventivi, Ordini and Fatture each have a Blocco Prodotti field configured against the Prodotti catalog', function () {
    seedCrmEntities();

    foreach (['preventivi', 'ordini_vendita', 'ordini_acquisto', 'fatture'] as $slug) {
        $entity = Entity::where('slug', $slug)->firstOrFail();
        $field = $entity->allFields()->firstWhere('column_name', 'righe_prodotto');

        expect($field)->not->toBeNull();
        expect($field->type->value)->toBe('products_block');
        expect($field->options['catalog_entity_slug'])->toBe('prodotti');
        expect($field->options['price_column'])->toBe('prezzo_di_listino');
        expect($field->options['total_target_column'])->toBe('totale');
        expect($field->required)->toBeTrue();
        expect(Schema::hasColumn($entity->table_name, 'righe_prodotto'))->toBeTrue();
    }
});

test('Opportunità is linked to Contatti via a many-to-many EntityRelation', function () {
    seedCrmEntities();

    $opportunita = Entity::where('slug', 'opportunita')->firstOrFail();
    $contatti = Entity::where('slug', 'contatti')->firstOrFail();

    $relation = EntityRelation::where('entity_a_id', $opportunita->id)->where('entity_b_id', $contatti->id)->first();

    expect($relation)->not->toBeNull();
});

test('Opportunità has a conditional field rule showing Motivo perdita only when Fase is persa', function () {
    seedCrmEntities();

    $entity = Entity::where('slug', 'opportunita')->firstOrFail();
    $condition = EntityFieldCondition::where('entity_id', $entity->id)->with('targets.field')->first();

    expect($condition)->not->toBeNull();
    expect($condition->rule)->toBe(['==' => [['var' => 'fase'], 'persa']]);
    expect($condition->targets->pluck('field.column_name')->all())->toBe(['motivo_perdita']);
});

test('Fatture has two conditional field rules for Tipo attiva/passiva', function () {
    seedCrmEntities();

    $entity = Entity::where('slug', 'fatture')->firstOrFail();
    $conditions = EntityFieldCondition::where('entity_id', $entity->id)->with('targets.field')->orderBy('position')->get();

    expect($conditions)->toHaveCount(2);

    $active = $conditions->firstWhere('rule', ['==' => [['var' => 'tipo'], 'attiva']]);
    $passive = $conditions->firstWhere('rule', ['==' => [['var' => 'tipo'], 'passiva']]);

    expect($active)->not->toBeNull();
    expect($passive)->not->toBeNull();
    expect($active->targets->pluck('field.column_name')->sort()->values()->all())->toBe(['cliente', 'ordine_vendita_collegato']);
    expect($passive->targets->pluck('field.column_name')->sort()->values()->all())->toBe(['fornitore', 'ordine_acquisto_collegato']);
});

test('Ticket has its two locked, hidden timer fields and a single Generale tab', function () {
    seedCrmEntities();

    $entity = Entity::where('slug', 'ticket')->firstOrFail();
    $fields = $entity->allFields();

    $startedAt = $fields->firstWhere('column_name', 'timer_avviato_il');
    $tracked = $fields->firstWhere('column_name', 'tempo_tracciato_minuti');

    expect($startedAt)->not->toBeNull();
    expect($startedAt->is_locked)->toBeTrue();
    expect($startedAt->is_hidden)->toBeTrue();
    expect($tracked)->not->toBeNull();
    expect($tracked->is_locked)->toBeTrue();
    expect($tracked->is_hidden)->toBeTrue();

    expect($fields->firstWhere('column_name', 'avvia_timer'))->toBeNull();
    expect($fields->firstWhere('column_name', 'ferma_timer'))->toBeNull();

    expect($entity->tabs)->toHaveCount(1);

    expect(Schema::hasColumns('entity_ticket', ['timer_avviato_il', 'tempo_tracciato_minuti']))->toBeTrue();
});

test('Ticket is linked to Calendario and Documenti via many-to-many EntityRelations', function () {
    seedCrmEntities();

    $ticket = Entity::where('slug', 'ticket')->firstOrFail();
    $calendario = Entity::where('slug', 'calendario')->firstOrFail();
    $documenti = Entity::where('slug', 'documenti')->firstOrFail();

    expect(EntityRelation::where('entity_a_id', $ticket->id)->where('entity_b_id', $calendario->id)->exists())->toBeTrue();
    expect(EntityRelation::where('entity_a_id', $ticket->id)->where('entity_b_id', $documenti->id)->exists())->toBeTrue();
});
