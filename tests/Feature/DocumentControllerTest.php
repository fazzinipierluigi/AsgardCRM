<?php

use Fazzinipierluigi\CrmCore\Models\DocumentFolder;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Database\Seeders\DocumentsEntitySeeder;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function documentsEntity(): Entity
{
    app(DocumentsEntitySeeder::class)->run(app(EntityInstaller::class));

    return Entity::where('slug', 'documenti')->firstOrFail();
}

test('an admin can create nested folders', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('documents.folders.store'), ['name' => 'Contratti', 'parent_id' => null])
        ->assertRedirect(route('documents.index'));
    $root = DocumentFolder::where('entity_id', $entity->id)->where('name', 'Contratti')->firstOrFail();

    $this->actingAs($admin)->post(route('documents.folders.store'), ['name' => '2026', 'parent_id' => $root->id])
        ->assertRedirect(route('documents.index', ['folder' => $root->id]));
    $child = DocumentFolder::where('entity_id', $entity->id)->where('name', '2026')->firstOrFail();

    expect($child->parent_id)->toBe($root->id);
});

test('two sibling folders cannot share the same name', function () {
    Storage::fake('local');
    documentsEntity();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('documents.folders.store'), ['name' => 'Contratti', 'parent_id' => null]);
    $this->actingAs($admin)->post(route('documents.folders.store'), ['name' => 'Contratti', 'parent_id' => null])
        ->assertSessionHasErrors();

    expect(DocumentFolder::where('name', 'Contratti')->count())->toBe(1);
});

test('a non-empty folder cannot be deleted', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $folder = DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => null, 'name' => 'Contratti', 'user_id' => $admin->id]);
    DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => $folder->id, 'name' => 'Sub', 'user_id' => $admin->id]);

    $this->actingAs($admin)->delete(route('documents.folders.destroy', $folder))
        ->assertSessionHas('error');

    expect(DocumentFolder::find($folder->id))->not->toBeNull();
});

test('an empty folder can be deleted', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $folder = DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => null, 'name' => 'Vuota', 'user_id' => $admin->id]);

    $this->actingAs($admin)->delete(route('documents.folders.destroy', $folder))
        ->assertRedirect(route('documents.index'));

    expect(DocumentFolder::find($folder->id))->toBeNull();
});

test('uploading a document stores the file on disk and creates the record in the chosen folder', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $folder = DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => null, 'name' => 'Contratti', 'user_id' => $admin->id]);
    $file = UploadedFile::fake()->create('contratto.pdf', 100, 'application/pdf');

    $this->actingAs($admin)->post(route('documents.store'), [
        'nome' => 'Contratto Rossi',
        'descrizione' => 'Firmato il 2026',
        'folder_id' => $folder->id,
        'file' => $file,
    ])->assertRedirect(route('documents.index', ['folder' => $folder->id]));

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->nome)->toBe('Contratto Rossi');
    expect($record->folder_id)->toBe($folder->id);
    expect($record->original_filename)->toBe('contratto.pdf');
    Storage::disk('local')->assertExists($record->stored_path);
});

test('an upload with a disallowed extension is rejected', function () {
    Storage::fake('local');
    documentsEntity();
    $admin = adminUser();
    $file = UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream');

    $this->actingAs($admin)->post(route('documents.store'), [
        'nome' => 'Malware',
        'folder_id' => null,
        'file' => $file,
    ])->assertSessionHasErrors('file');

    expect(EntityRecord::forEntity(Entity::where('slug', 'documenti')->firstOrFail())->newQuery()->count())->toBe(0);
});

test('downloading a document streams the original file with its original name', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $file = UploadedFile::fake()->create('contratto.pdf', 10, 'application/pdf');
    $this->actingAs($admin)->post(route('documents.store'), ['nome' => 'X', 'folder_id' => null, 'file' => $file]);
    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();

    $response = $this->actingAs($admin)->get(route('documents.download', $record));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('contratto.pdf');
});

test('editing a document can rename it, move it to another folder, and replace its file', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $folder = DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => null, 'name' => 'Nuova', 'user_id' => $admin->id]);
    $file = UploadedFile::fake()->create('old.pdf', 10, 'application/pdf');
    $this->actingAs($admin)->post(route('documents.store'), ['nome' => 'Vecchio nome', 'folder_id' => null, 'file' => $file]);
    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    $oldPath = $record->stored_path;

    $newFile = UploadedFile::fake()->create('new.pdf', 10, 'application/pdf');
    $this->actingAs($admin)->put(route('documents.update', $record), [
        'nome' => 'Nuovo nome',
        'folder_id' => $folder->id,
        'file' => $newFile,
    ])->assertRedirect(route('documents.index', ['folder' => $folder->id]));

    $record->refresh();
    expect($record->nome)->toBe('Nuovo nome');
    expect($record->folder_id)->toBe($folder->id);
    expect($record->original_filename)->toBe('new.pdf');
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($record->stored_path);
});

test('deleting a document soft-deletes it, and it appears in the Cestino', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
    $this->actingAs($admin)->post(route('documents.store'), ['nome' => 'Da eliminare', 'folder_id' => null, 'file' => $file]);
    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();

    $this->actingAs($admin)->delete(route('documents.destroy', $record))
        ->assertRedirect(route('documents.index'));

    expect(EntityRecord::forEntity($entity)->newQuery()->count())->toBe(0);
    expect(EntityRecord::forEntity($entity)->newQuery()->onlyTrashed()->count())->toBe(1);
    Storage::disk('local')->assertExists($record->stored_path);

    $this->actingAs($admin)->delete(route('trash.force-delete', [$entity, $record]))
        ->assertRedirect(route('trash.index', ['entity' => $entity->slug]));

    Storage::disk('local')->assertMissing($record->stored_path);
});

test('a user without the entity_documenti permissions is forbidden', function () {
    documentsEntity();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('documents.index'))->assertForbidden();
});

test('a user with the entity_documenti.index permission can browse', function () {
    documentsEntity();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-documenti']);
    $role->givePermission(Permission::where('key', 'entity_documenti.index')->firstOrFail());
    $user->assignRole($role);

    $this->actingAs($user)->get(route('documents.index'))->assertOk();
});

test('the folder tree sidebar reflects nested folders and the search filter searches across the whole entity', function () {
    Storage::fake('local');
    $entity = documentsEntity();
    $admin = adminUser();
    $root = DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => null, 'name' => 'Contratti', 'user_id' => $admin->id]);
    $child = DocumentFolder::create(['entity_id' => $entity->id, 'parent_id' => $root->id, 'name' => '2026', 'user_id' => $admin->id]);
    $file = UploadedFile::fake()->create('trovami.pdf', 10, 'application/pdf');
    $this->actingAs($admin)->post(route('documents.store'), ['nome' => 'Trovami', 'folder_id' => $child->id, 'file' => $file]);

    $response = $this->actingAs($admin)->get(route('documents.index'));
    $response->assertOk()->assertSee('Contratti');

    $searchResponse = $this->actingAs($admin)->get(route('documents.index', ['q' => 'Trova']));
    $searchResponse->assertOk()->assertSee('Trovami');
});
