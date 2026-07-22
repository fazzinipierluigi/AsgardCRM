@extends('layouts.admin')

@section('title', $workflow->name)

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.workflows.index') }}">{{ t('Workflows') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.workflows.show', $workflow) }}">{{ $workflow->name }}</a>
    </li>
@endsection

@section('buttons')
    <form action="{{ route('admin.workflows.run', $workflow) }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-primary" data-testid="workflow-run-now">{{ t('Avvia istanza') }}</button>
    </form>
    <a href="{{ route('admin.workflows.builder.edit', $workflow) }}" class="btn btn-outline-primary" data-testid="workflow-edit-link">{{ t('Modifica') }}</a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="workflow-status">
            @switch(session('status'))
                @case('workflow-run-started')
                    {{ t('Istanza avviata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" data-testid="workflow-error">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-3">{{ t('Descrizione') }}</dt>
                <dd class="col-9">{{ $workflow->description ?: '—' }}</dd>

                <dt class="col-3">{{ t('Attivo') }}</dt>
                <dd class="col-9">{{ $workflow->is_active ? t('Sì') : t('No') }}</dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ t('Istanze') }}</h3>
        </div>
        <div id="workflow-instances-grid" data-testid="workflow-instances-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                columns: [
                    { id: 'id', index: 'id', text: '#', sortable: true },
                    { id: 'status', index: 'status', text: @json(t('Stato')), sortable: true },
                    { id: 'entity_type', index: 'entity_type', text: @json(t('Entità collegata')) },
                    { id: 'started_at', index: 'started_at', text: @json(t('Iniziata il')), sortable: true },
                    { id: 'ended_at', index: 'ended_at', text: @json(t('Terminata il')), sortable: true },
                    { id: 'error_message', index: 'error_message', text: @json(t('Errore')) },
                ],
                serverAdapter: {
                    url: @json(route('admin.workflows.instances.data', $workflow)),
                    method: 'GET',
                },
            }).render('#workflow-instances-grid');
        });
    </script>
@endsection
