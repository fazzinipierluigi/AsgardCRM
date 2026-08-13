@extends('layouts.admin')

@section('title', $importer->title)

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.importers.index') }}">{{ t('Importatori') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.importers.show', $importer) }}">{{ $importer->title }}</a>
    </li>
@endsection

@section('buttons')
    @if ($importer->schedule_type->runsManually())
        <form action="{{ route('admin.importers.run', $importer) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary" data-testid="importer-run-now">{{ t('Esegui ora') }}</button>
        </form>
    @endif
    <a href="{{ route('admin.importers.edit', $importer) }}" class="btn btn-outline-primary" data-testid="importer-edit-link">{{ t('Modifica') }}</a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="importer-status">
            @switch(session('status'))
                @case('importer-run-dispatched')
                    {{ t("Esecuzione avviata: verrà elaborata in background.") }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-3">{{ t('Entità') }}</dt>
                <dd class="col-9">{{ $importer->entity->name }}</dd>

                <dt class="col-3">{{ t('Canale') }}</dt>
                <dd class="col-9">{{ $importer->channel->label() }}</dd>

                <dt class="col-3">{{ t('Programmazione') }}</dt>
                <dd class="col-9">
                    {{ $importer->schedule_type->label() }}
                    @if ($importer->cron_expression)
                        <code>{{ $importer->cron_expression }}</code>
                    @endif
                </dd>

                <dt class="col-3">{{ t('Attivo') }}</dt>
                <dd class="col-9">{{ $importer->is_active ? t('Sì') : t('No') }}</dd>

                <dt class="col-3">{{ t('Ultima esecuzione') }}</dt>
                <dd class="col-9">
                    {{ $importer->last_run_at?->format('d/m/Y H:i') ?? t('Mai eseguito') }}
                    @if ($importer->last_run_status)
                        — {{ $importer->last_run_status }}
                    @endif
                </dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ t('Storico esecuzioni') }}</h3>
        </div>
        <div id="importer-runs-grid" data-testid="importer-runs-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                columns: [
                    { id: 'started_at', index: 'started_at', text: @json(t('Iniziata il')), sortable: true },
                    { id: 'finished_at', index: 'finished_at', text: @json(t('Terminata il')), sortable: true },
                    { id: 'status', index: 'status', text: @json(t('Stato')) },
                    { id: 'rows_imported', index: 'rows_imported', text: @json(t('Righe importate')) },
                    { id: 'rows_failed', index: 'rows_failed', text: @json(t('Righe fallite')) },
                    { id: 'error_message', index: 'error_message', text: @json(t('Errore')) },
                ],
                serverAdapter: {
                    url: @json(route('admin.importers.runs.data', $importer)),
                    method: 'GET',
                },
            }).render('#importer-runs-grid');
        });
    </script>
@endsection
