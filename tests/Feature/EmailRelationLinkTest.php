<?php

use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\EntityRelation;
use App\Models\EntityRelationLink;
use Database\Seeders\EmailEntitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Proves that once an "E-mail" entity_email bookmark row exists (the
 * only thing MailController::attach() ever writes, see
 * MailControllerTest), linking it to another entity's record goes
 * entirely through the app's existing, unmodified N:M relation system
 * (EntityRelation/EntityRelationLink/EntityRelationLinkController) —
 * no email-specific relation code exists or is needed.
 */
test('an entity_email bookmark can be linked to another entity record via the existing N:M relation system', function () {
    test()->seed(EmailEntitySeeder::class);
    $emailEntity = Entity::where('slug', 'email')->firstOrFail();
    $ticketEntity = relationTestEntity('link-ticket', 'Ticket');
    $relation = EntityRelation::create(['entity_a_id' => $emailEntity->id, 'entity_b_id' => $ticketEntity->id, 'name' => 'Ticket collegati']);

    $admin = adminUser();
    $emailRecord = EntityRecord::forEntity($emailEntity)->newQuery()->create([
        'user_id' => $admin->id, 'oggetto' => 'Richiesta assistenza', 'mail_account_id' => 1, 'folder' => 'INBOX', 'message_uid' => '42',
    ]);
    $ticketRecord = EntityRecord::forEntity($ticketEntity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Stampante rotta']);

    $this->actingAs($admin)->postJson(route('entities.relations.attach', [$emailEntity, $emailRecord, $relation]), [
        'target_record_id' => $ticketRecord->id,
    ])->assertOk();

    expect(EntityRelationLink::count())->toBe(1);

    $response = $this->actingAs($admin)->getJson(route('entities.relations.data', [$emailEntity, $emailRecord, $relation]));

    $response->assertOk();
    expect($response->json('0.label'))->toBe('Stampante rotta');
    expect($response->json('0.url'))->toBe(route('entities.show', [$ticketEntity, $ticketRecord]));
});
