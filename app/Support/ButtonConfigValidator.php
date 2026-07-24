<?php

namespace App\Support;

use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTriggerType;
use App\Models\Workflow;

/**
 * Validation and options-array parsing for a "button" config
 * (button_action/button_workflow_id/button_importer_ids/button_javascript),
 * shared by every place that lets an admin configure one of these three
 * actions: the entity Button field (StoreEntityFieldRequest,
 * UpdateEntityBuilderRequest, EntityFieldController, EntityBuilderController)
 * and the entity list's global button widget (StoreEntityListWidgetRequest,
 * EntityListWidgetController).
 */
class ButtonConfigValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string> Validation errors keyed by field name.
     */
    public static function errors(array $input): array
    {
        $errors = [];
        $action = $input['button_action'] ?? null;

        if (! in_array($action, ['workflow', 'importer', 'javascript'], true)) {
            $errors['button_action'] = 'Seleziona l\'azione del bottone.';

            return $errors;
        }

        if ($action === 'workflow') {
            $workflowId = $input['button_workflow_id'] ?? null;

            if (empty($workflowId)) {
                $errors['button_workflow_id'] = 'Seleziona il workflow da avviare.';
            } elseif (! self::isManualWorkflow($workflowId)) {
                $errors['button_workflow_id'] = 'Il workflow selezionato non è attivo o non ha un avvio manuale.';
            }
        }

        if ($action === 'importer' && empty($input['button_importer_ids'] ?? null)) {
            $errors['button_importer_ids'] = 'Seleziona almeno un importatore.';
        }

        if ($action === 'javascript' && trim((string) ($input['button_javascript'] ?? '')) === '') {
            $errors['button_javascript'] = 'Il codice JavaScript è obbligatorio.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $input  button_importer_ids may be a
     *                                       plain array (multi-select) or
     *                                       a comma-separated string (the
     *                                       pre-install structural builder's
     *                                       single hidden-input convention).
     * @return array{button_action: ?string, button_workflow_id: ?int, button_importer_ids: list<int>, button_javascript: ?string}
     */
    public static function parse(array $input): array
    {
        $action = $input['button_action'] ?? null;
        $importerIds = $input['button_importer_ids'] ?? [];

        if (is_string($importerIds)) {
            $importerIds = array_filter(array_map('trim', explode(',', $importerIds)), fn ($id) => $id !== '');
        }

        return [
            'button_action' => $action,
            'button_workflow_id' => $action === 'workflow' ? (int) ($input['button_workflow_id'] ?? 0) : null,
            'button_importer_ids' => $action === 'importer' ? array_values(array_map('intval', $importerIds)) : [],
            'button_javascript' => $action === 'javascript' ? (string) ($input['button_javascript'] ?? '') : null,
        ];
    }

    public static function isManualWorkflow(mixed $workflowId): bool
    {
        return Workflow::where('id', $workflowId)
            ->where('is_active', true)
            ->whereHas('currentVersion.nodes', function ($query) {
                $query->where('type', WorkflowNodeType::Start->value)
                    ->where('config->trigger_type', WorkflowTriggerType::Manual->value);
            })
            ->exists();
    }
}
