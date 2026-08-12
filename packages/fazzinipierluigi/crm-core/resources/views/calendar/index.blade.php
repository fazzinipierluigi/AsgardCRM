@extends('layouts.base')

@section('title', t('Calendario'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('calendar.index') }}">{{ t('Calendario') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('calendar.settings.edit') }}" class="btn btn-outline-secondary" data-testid="calendar-settings-link">
        {{ t('Impostazioni calendario') }}
    </a>
    @if ($canCreate)
        <button type="button" class="btn btn-primary" id="calendar-new-event-btn" data-testid="calendar-new-event">
            {{ t('Nuovo evento') }}
        </button>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <div id="calendar" data-testid="calendar-root"></div>
        </div>
    </div>

    <div class="modal" id="calendar-event-modal" tabindex="-1" data-testid="calendar-event-modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="calendar-event-form" data-testid="calendar-event-form">
                    <div class="modal-header">
                        <h5
                            class="modal-title"
                            id="calendar-event-modal-title"
                            data-new-label="{{ t('Nuovo evento') }}"
                            data-edit-label="{{ t('Modifica evento') }}"
                        >{{ t('Nuovo evento') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="calendar-event-id">

                        <div class="mb-3">
                            <label class="form-label">{{ t('Titolo') }}</label>
                            <input type="text" class="form-control" id="calendar-event-title" data-testid="calendar-event-title" required>
                            <div class="invalid-feedback d-block d-none" data-field-error="title"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ t('Descrizione') }}</label>
                            <textarea class="form-control" id="calendar-event-description" rows="2"></textarea>
                            <div class="invalid-feedback d-block d-none" data-field-error="description"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ t('Data/ora inizio') }}</label>
                                <input type="datetime-local" class="form-control" id="calendar-event-start" data-testid="calendar-event-start" required>
                                <div class="invalid-feedback d-block d-none" data-field-error="start_datetime"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ t('Data/ora fine') }}</label>
                                <input type="datetime-local" class="form-control" id="calendar-event-end" data-testid="calendar-event-end" required>
                                <div class="invalid-feedback d-block d-none" data-field-error="end_datetime"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ t('Mostra come') }}</label>
                                <select class="form-select" id="calendar-event-show-as">
                                    <option value="available">{{ t('Disponibile') }}</option>
                                    <option value="busy" selected>{{ t('Occupato') }}</option>
                                    <option value="out_of_office">{{ t('Fuori sede') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ t('Stato') }}</label>
                                <select class="form-select" id="calendar-event-status">
                                    <option value="tentative">{{ t('Provvisorio') }}</option>
                                    <option value="confirmed" selected>{{ t('Confermato') }}</option>
                                    <option value="cancelled">{{ t('Annullato') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ t('Relazione verso') }}</label>
                                <select class="form-select" id="calendar-event-relatable-type" data-tom-select-manual>
                                    <option value="">{{ t('Seleziona...') }}</option>
                                    @foreach ($relationTargets as $group => $targets)
                                        <optgroup label="{{ $group }}">
                                            @foreach ($targets as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">&nbsp;</label>
                                <select class="form-select" id="calendar-event-relatable-id" data-tom-select-manual></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-outline-danger d-none" id="calendar-event-delete-btn" data-testid="calendar-event-delete">
                            {{ t('Elimina evento') }}
                        </button>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-link" data-bs-dismiss="modal">{{ t('Annulla') }}</button>
                            <button type="submit" class="btn btn-primary" data-testid="calendar-event-save">{{ t('Salva') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.CALENDAR_ROUTES = {
            events: @json(route('calendar.events')),
            store: @json(route('calendar.events.store')),
            update: @json(url('calendar/events') . '/__ID__'),
            destroy: @json(url('calendar/events') . '/__ID__'),
            relatables: @json(route('calendar.relatables')),
        };
        window.CALENDAR_CAN_CREATE = @json($canCreate);
    </script>

    @vite('resources/js/calendar.js')
@endsection
