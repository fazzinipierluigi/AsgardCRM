@extends('layouts.admin')

@section('title', t('Workflows'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.workflows.index') }}">{{ t('Workflows') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.workflows.import.form') }}" class="btn btn-outline-secondary" data-testid="workflow-import-link">
        {{ t('Importa') }}
    </a>
    <a href="{{ route('admin.workflows.create') }}" class="btn btn-primary" data-testid="workflow-create-link">
        {{ t('Nuovo workflow') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="workflows-status">
            @switch(session('status'))
                @case('workflow-updated')
                    {{ t('Workflow aggiornato correttamente.') }}
                    @break
                @case('workflow-deleted')
                    {{ t('Workflow eliminato correttamente.') }}
                    @break
                @case('workflow-imported')
                    {{ t('Workflow importato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" data-testid="workflows-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div id="workflows-grid" data-testid="workflows-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                filterBar: true,
                columns: [
                    { id: 'name', index: 'name', text: @json(t('Nome')), sortable: true, filterable: true },
                    { id: 'description', index: 'description', text: @json(t('Descrizione')), filterable: true },
                    { id: 'is_active', index: 'is_active', text: @json(t('Attivo')), type: 'boolean' },
                    { id: 'instances_count', index: 'instances_count', text: @json(t('Istanze')), sortable: true },
                    { id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var baseUrl = @json(route('admin.workflows.index')) + '/' + id;
                            var showUrl = baseUrl;
                            var editUrl = baseUrl + '/builder';
                            var runUrl = baseUrl + '/run';
                            return '<a href="' + showUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Dettaglio')) + '</a>' +
                                '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>' +
                                '<form method="POST" action="' + runUrl + '" style="display:inline" class="me-1">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-success">' + @json(t('Avvia')) + '</button>' +
                                '</form>' +
                                '<form method="POST" action="' + baseUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina')) + '</button>' +
                                '</form>';
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('admin.workflows.data')),
                    method: 'GET',
                },
            }).render('#workflows-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
