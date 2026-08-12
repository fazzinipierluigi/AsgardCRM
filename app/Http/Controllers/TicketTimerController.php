<?php

namespace App\Http\Controllers;

use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityRelation;
use Fazzinipierluigi\CrmCore\Services\EntityRecordAuthorizer;
use Fazzinipierluigi\CrmCore\Services\EntityRelationLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Backs the live timer rendered in the Ticket record page's header
 * buttons (see resources/views/entities/_ticket-timer-buttons.blade.php
 * and resources/js/ticket-timer.js) — not a generic entity feature, so
 * it gets its own dedicated routes/controller rather than going through
 * EntityFieldButtonController.
 *
 * start() stamps `timer_avviato_il`; stop() reads it back, adds the
 * elapsed seconds (as minutes) to `tempo_tracciato_minuti`, clears the
 * start stamp, and creates a linked record in the "Calendario" system
 * entity — the one piece of this that IS just the existing generic
 * entity-record machinery, the same way CalendarController::store()
 * creates one, using the same relatable_type/relatable_id pair every
 * calendar entry has (see EntitySchemaBuilder). Both columns are
 * is_hidden EntityFields on "ticket" (see TicketEntitySeeder) — real
 * storage, never rendered in the record form/view, never touched by
 * EntityRecordController's generic save path.
 *
 * A user can run this timer on several tickets at once — state lives
 * per-record, there's no app-wide "the" timer.
 */
class TicketTimerController extends Controller
{
    public function __construct(
        private readonly EntityRecordAuthorizer $authorizer,
        private readonly EntityRelationLinkResolver $relationLinkResolver,
    ) {}

    public function start(int $record): JsonResponse
    {
        $entity = $this->ticketEntity();
        $recordModel = $this->authorizedRecord($entity, $record);

        if ($recordModel->timer_avviato_il !== null) {
            return response()->json(['message' => 'Il timer è già avviato.'], 422);
        }

        $startedAt = now();
        $recordModel->update(['timer_avviato_il' => $startedAt]);

        return response()->json([
            'message' => 'Timer avviato.',
            'started_at' => $startedAt->toIso8601String(),
        ]);
    }

    public function stop(int $record): JsonResponse
    {
        $entity = $this->ticketEntity();
        $recordModel = $this->authorizedRecord($entity, $record);

        if ($recordModel->timer_avviato_il === null) {
            return response()->json(['message' => 'Il timer non è avviato.'], 422);
        }

        $startedAt = Carbon::parse($recordModel->timer_avviato_il);
        $stoppedAt = now();
        $elapsedSeconds = max(0, $startedAt->diffInSeconds($stoppedAt));

        $recordModel->update([
            'tempo_tracciato_minuti' => (float) ($recordModel->tempo_tracciato_minuti ?? 0) + ($elapsedSeconds / 60),
            'timer_avviato_il' => null,
        ]);

        $this->createCalendarEntry($entity, $recordModel, $startedAt, $stoppedAt);

        return response()->json([
            'message' => "Timer fermato: {$this->formatDuration($elapsedSeconds)} registrati.",
            'elapsed_seconds' => $elapsedSeconds,
        ]);
    }

    private function formatDuration(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    private function createCalendarEntry(Entity $ticketEntity, EntityRecord $ticket, Carbon $startedAt, Carbon $stoppedAt): void
    {
        $calendarEntity = Entity::where('slug', 'calendario')->where('is_installed', true)->first();

        if ($calendarEntity === null) {
            return;
        }

        $label = $ticket->numero ?? "#{$ticket->id}";

        $calendarRecord = EntityRecord::forEntity($calendarEntity)->newQuery()->create([
            'user_id' => request()->user()->id,
            'title' => "Lavorazione ticket {$label} — {$ticket->oggetto}",
            'description' => null,
            'show_as' => 'busy',
            'status' => 'confirmed',
            'start_datetime' => $startedAt,
            'end_datetime' => $stoppedAt,
            'relatable_type' => 'entity:ticket',
            'relatable_id' => $ticket->id,
        ]);

        $relation = EntityRelation::where('entity_a_id', $ticketEntity->id)
            ->where('entity_b_id', $calendarEntity->id)
            ->first();

        if ($relation !== null) {
            $this->relationLinkResolver->attach($relation, $ticketEntity, $ticket->id, $calendarRecord->id);
        }
    }

    private function ticketEntity(): Entity
    {
        return Entity::where('slug', 'ticket')->firstOrFail();
    }

    private function authorizedRecord(Entity $entity, int $recordId): EntityRecord
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot('entity_ticket.edit')) {
            abort(403);
        }

        $record = EntityRecord::forEntity($entity)->newQuery()->findOrFail($recordId);

        if (! $this->authorizer->canEdit(request()->user(), $entity, $record->user_id)) {
            abort(403);
        }

        return $record;
    }
}
