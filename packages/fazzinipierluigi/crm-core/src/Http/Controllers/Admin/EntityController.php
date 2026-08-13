<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\ImportEntityRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreEntityRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateEntityRequest;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Services\EntitySchemaTransfer;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EntityController extends Controller
{
    /**
     * Display the entities listing page.
     */
    public function index(): View
    {
        return view('crm::admin.entities.index');
    }

    /**
     * Serve the server-side datatable request for the entities listing.
     */
    public function data(Request $request): JsonResponse
    {
        $entities = Entity::select('id', 'name', 'slug', 'icon', 'is_system', 'is_installed', 'created_at');

        $source = new EloquentSource;
        $source->apply($entities, $request, null, ['name', 'slug']);

        return $source->getResponse(function (Entity $entity) {
            return [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'icon' => $entity->icon,
                'is_system' => $entity->is_system,
                'is_installed' => $entity->is_installed,
                'created_at' => $entity->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * Show the form to create a new entity.
     */
    public function create(): View
    {
        return view('crm::admin.entities.create');
    }

    /**
     * Persist a new entity's metadata and send the admin to design its
     * tabs/cards/fields next.
     */
    public function store(StoreEntityRequest $request): RedirectResponse
    {
        $slug = Entity::uniqueSlug($request->string('name'));

        $entity = Entity::create([
            'name' => $request->string('name'),
            'slug' => $slug,
            'table_name' => 'entity_'.$slug,
            'icon' => $request->string('icon')->value() ?: null,
        ]);

        return redirect()->route('admin.entities.builder.edit', $entity)->with('status', 'entity-created');
    }

    /**
     * Show the form to edit an existing entity's metadata.
     */
    public function edit(Entity $entity): View
    {
        return view('crm::admin.entities.edit', ['entity' => $entity]);
    }

    /**
     * Update an existing entity's metadata (name/icon only — the slug and
     * table name never change once created).
     */
    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        $entity->update([
            'name' => $request->string('name'),
            'icon' => $request->string('icon')->value() ?: null,
        ]);

        return redirect()->route('admin.entities.index')->with('status', 'entity-updated');
    }

    /**
     * Delete an entity. Refused for system entities and for installed
     * entities (uninstall first).
     */
    public function destroy(Entity $entity): RedirectResponse
    {
        if ($entity->is_system) {
            return back()->with('error', 'Non è possibile eliminare un\'entità di sistema.');
        }

        if ($entity->is_installed) {
            return back()->with('error', 'Disinstalla l\'entità prima di eliminarla.');
        }

        $entity->delete();

        return redirect()->route('admin.entities.index')->with('status', 'entity-deleted');
    }

    /**
     * Install an entity: create its dedicated table and CRUD permissions.
     */
    public function install(Entity $entity, EntityInstaller $installer): RedirectResponse
    {
        try {
            $installer->install($entity);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'entity-installed');
    }

    /**
     * Uninstall an entity: drop its table and CRUD permissions. Refused
     * for system entities.
     */
    public function uninstall(Entity $entity, EntityInstaller $installer): RedirectResponse
    {
        try {
            $installer->uninstall($entity);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'entity-uninstalled');
    }

    /**
     * Download an entity's tab/card/field structure as a JSON schema
     * file (no record data — just the design).
     */
    public function export(Entity $entity, EntitySchemaTransfer $transfer): JsonResponse
    {
        return response()->json($transfer->export($entity))
            ->header('Content-Disposition', 'attachment; filename="entity-'.$entity->slug.'.json"');
    }

    /**
     * Show the schema import form.
     */
    public function importForm(): View
    {
        return view('crm::admin.entities.import');
    }

    /**
     * Create a new custom, not-yet-installed entity from an uploaded
     * JSON schema file (as produced by export()).
     */
    public function import(ImportEntityRequest $request, EntitySchemaTransfer $transfer): RedirectResponse
    {
        $data = json_decode($request->file('file')->get(), true);

        if (! is_array($data)) {
            return back()->with('error', 'Il file non è un JSON valido.');
        }

        try {
            $entity = $transfer->import($data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.entities.builder.edit', $entity)->with('status', 'entity-imported');
    }
}
