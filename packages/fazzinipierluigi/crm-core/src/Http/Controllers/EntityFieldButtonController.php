<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Jobs\RunImporterJob;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Services\EntityRecordAuthorizer;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Executes a Button field's configured action against one entity
 * record: starts a manual workflow bound to that record, or dispatches
 * one or more Importer runs. The "javascript" action never reaches
 * here — its code ships inline in the field's HTML and runs entirely
 * client-side (see resources/js/entity-button-field.js).
 */
class EntityFieldButtonController extends Controller
{
    public function __construct(
        private readonly EntityRecordAuthorizer $authorizer,
        private readonly WorkflowEngine $engine,
    ) {}

    public function trigger(Entity $entity, int $record, EntityField $field): JsonResponse
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        $entity->load('tabs.cards.fields');

        if ($field->type !== EntityFieldType::Button || ! $entity->allFields()->contains('id', $field->id)) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_{$entity->slug}.edit")) {
            abort(403);
        }

        $recordModel = EntityRecord::forEntity($entity)->newQuery()->findOrFail($record);

        if (! $this->authorizer->canEdit(request()->user(), $entity, $recordModel->user_id)) {
            abort(403);
        }

        return match ($field->options['button_action'] ?? null) {
            'workflow' => $this->runWorkflow($field, $entity, $recordModel),
            'importer' => $this->runImporters($field),
            default => response()->json(['message' => 'Nessuna azione configurata per questo bottone.'], 422),
        };
    }

    private function runWorkflow(EntityField $field, Entity $entity, EntityRecord $record): JsonResponse
    {
        $workflow = Workflow::find($field->options['button_workflow_id'] ?? null);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow non trovato.'], 422);
        }

        try {
            $instance = $this->engine->start($workflow, [], $record, entitySlug: $entity->slug);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($instance === null) {
            return response()->json(['message' => 'La condizione di avvio non è soddisfatta.'], 422);
        }

        return response()->json(['message' => 'Flusso avviato correttamente.']);
    }

    private function runImporters(EntityField $field): JsonResponse
    {
        $importerIds = $field->options['button_importer_ids'] ?? [];

        $importers = Importer::whereIn('id', $importerIds)->get();

        foreach ($importers as $importer) {
            RunImporterJob::dispatch($importer);
        }

        return response()->json(['message' => 'Importazione avviata.']);
    }
}
