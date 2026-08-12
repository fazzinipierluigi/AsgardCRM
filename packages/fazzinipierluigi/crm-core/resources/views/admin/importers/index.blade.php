@extends('layouts.admin')

@section('title', t('Importatori'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.importers.index') }}">{{ t('Importatori') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.importers.create') }}" class="btn btn-primary" data-testid="importer-create-link">
        {{ t('Nuovo importatore') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="importers-status">
            @switch(session('status'))
                @case('importer-created')
                    {{ t('Importatore creato correttamente.') }}
                    @break
                @case('importer-updated')
                    {{ t('Importatore aggiornato correttamente.') }}
                    @break
                @case('importer-deleted')
                    {{ t('Importatore eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="importers-grid" data-testid="importers-grid"></div>
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
                    { id: 'title', index: 'title', text: @json(t('Titolo')), sortable: true, filterable: true },
                    { id: 'entity', index: 'entity', text: @json(t('Entità')), sortable: true, filterable: true },
                    { id: 'channel', index: 'channel', text: @json(t('Canale')), sortable: true, filterable: true },
                    { id: 'schedule_type', index: 'schedule_type', text: @json(t('Programmazione')), filterable: true },
                    { id: 'is_active', index: 'is_active', text: @json(t('Attivo')), type: 'boolean' },
                    { id: 'last_run_at', index: 'last_run_at', text: @json(t('Ultima esecuzione')), sortable: true },
                    { id: 'last_run_status', index: 'last_run_status', text: @json(t('Ultimo esito')) },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var showUrl = @json(route('admin.importers.index')) + '/' + id;
                            var editUrl = showUrl + '/edit';
                            return '<a href="' + showUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Dettaglio')) + '</a>' +
                                '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>' +
                                '<form method="POST" action="' + showUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina')) + '</button>' +
                                '</form>';
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('admin.importers.data')),
                    method: 'GET',
                },
            }).render('#importers-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
