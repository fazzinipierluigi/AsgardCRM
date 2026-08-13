<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regression coverage for the stored-XSS fix in
 * EntityRecordController::sanitizeRichText(): the previous
 * strip_tags($value, $allowedTags) implementation only removed
 * disallowed tag *names*, leaving attributes (onmouseover=, a
 * javascript: href) on the tags it kept — this suite proves the
 * DOMDocument-based replacement actually strips them.
 */
function installedEntityWithRichTextField(): Entity
{
    $entity = Entity::create(['name' => 'Note', 'slug' => 'note', 'table_name' => 'entity_note']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Contenuto', 'position' => 0]);
    $card->fields()->create(['name' => 'Testo', 'column_name' => 'testo', 'type' => EntityFieldType::RichText, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

test('an event handler attribute is stripped from an allowed tag', function () {
    $entity = installedEntityWithRichTextField();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('entities.store', $entity), [
        'testo' => '<b onmouseover="fetch(\'https://evil.example\')">hover me</b>',
    ]);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->testo)->toBe('<b>hover me</b>');
});

test('a javascript: link is stripped down to plain text', function () {
    $entity = installedEntityWithRichTextField();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('entities.store', $entity), [
        'testo' => '<a href="javascript:alert(document.cookie)">click me</a>',
    ]);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->testo)->toBe('click me');
});

test('a script tag is removed and its content is not rendered as markup', function () {
    $entity = installedEntityWithRichTextField();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('entities.store', $entity), [
        'testo' => '<p>Nota</p><script>alert(1)</script>',
    ]);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->testo)->not->toContain('<script');
});

test('allowed formatting tags survive with no attributes stripped away in content', function () {
    $entity = installedEntityWithRichTextField();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('entities.store', $entity), [
        'testo' => '<p>Elenco:</p><ul><li><b>Uno</b></li><li><i>Due</i></li></ul>',
    ]);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->testo)->toBe('<p>Elenco:</p><ul><li><b>Uno</b></li><li><i>Due</i></li></ul>');
});
