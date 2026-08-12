@php
    $timerRunning = $record !== null && $record->timer_avviato_il !== null;
    $timerStartedAtIso = $timerRunning ? \Illuminate\Support\Carbon::parse($record->timer_avviato_il)->toIso8601String() : null;
@endphp

@if ($record)
    <div
        class="d-flex align-items-center gap-2 me-2 p-1 ps-2 bg-white rounded"
        data-ticket-timer
        data-start-url="{{ route('tickets.timer.start', $record->id) }}"
        data-stop-url="{{ route('tickets.timer.stop', $record->id) }}"
        data-running="{{ $timerRunning ? '1' : '0' }}"
        data-started-at="{{ $timerStartedAtIso }}"
        data-testid="ticket-timer"
    >
        <span class="font-monospace fs-4" data-ticket-timer-display data-testid="ticket-timer-display">00:00:00</span>
        <button
            type="button"
            class="btn btn-icon {{ $timerRunning ? 'btn-danger' : 'btn-primary' }}"
            data-ticket-timer-toggle
            data-testid="ticket-timer-toggle"
            title="{{ $timerRunning ? t('Ferma timer') : t('Avvia timer') }}"
            aria-label="{{ $timerRunning ? t('Ferma timer') : t('Avvia timer') }}"
        >{!! icon($timerRunning ? 'player-stop' : 'player-play') !!}</button>
    </div>

    <script>
        window.TICKET_TIMER_I18N = window.TICKET_TIMER_I18N || {
            start: @json(t('Avvia timer')),
            stop: @json(t('Ferma timer')),
            iconPlay: @json(icon('player-play')),
            iconStop: @json(icon('player-stop')),
        };
    </script>

    @vite('resources/js/ticket-timer.js', 'vendor/crm')
@endif
