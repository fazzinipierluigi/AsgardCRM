<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\EntityField;
use App\Services\Workflows\WorkflowFieldReferenceScanner;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adding a field to an installed entity happens inline in the same
 * builder UI used before install (see EntityBuilderController) — one
 * interface for the same action, regardless of whether the entity is
 * installed yet. This controller only exposes the pre-delete usage
 * check that UI relies on.
 */
class EntityFieldController extends Controller
{
    /**
     * Called via AJAX right before the builder actually removes an
     * existing field, so the admin gets an up-front, evident warning
     * (a SweetAlert) about what references exist across every workflow —
     * not just this narrow flow's own point of view.
     */
    public function usage(Entity $entity, EntityField $field, WorkflowFieldReferenceScanner $scanner): JsonResponse
    {
        if ($field->card?->tab?->entity_id !== $entity->id) {
            throw new NotFoundHttpException;
        }

        return response()->json($scanner->scan($entity, $field));
    }
}
