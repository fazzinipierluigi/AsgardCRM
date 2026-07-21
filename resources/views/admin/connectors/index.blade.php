@extends('layouts.admin')

@section('title', t('Connettori'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.connectors.index') }}">{{ t('Connettori') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.connectors.create') }}" class="btn btn-primary" data-testid="connector-create-link">
        {{ t('Nuovo connettore') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="connectors-status">
            @switch(session('status'))
                @case('connector-created')
                    {{ t('Connettore creato correttamente.') }}
                    @break
                @case('connector-updated')
                    {{ t('Connettore aggiornato correttamente.') }}
                    @break
                @case('connector-deleted')
                    {{ t('Connettore eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="connectors-grid" data-testid="connectors-grid"></div>
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
                    { id: 'type', index: 'type', text: @json(t('Tipo connettore')), sortable: true, filterable: true },
                    { id: 'is_active', index: 'is_active', text: @json(t('Attivo')), type: 'boolean' },
                    { id: 'sync_direction', index: 'sync_direction', text: @json(t('Direzione sincronizzazione')), filterable: true },
                    { id: 'last_synced_at', index: 'last_synced_at', text: @json(t('Ultima sincronizzazione')), sortable: true },
                    { id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true, filterable: false },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.connectors.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.connectors.index')) + '/' + id;
                            return '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>' +
                                '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina')) + '</button>' +
                                '</form>';
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('admin.connectors.data')),
                    method: 'GET',
                },
            }).render('#connectors-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
