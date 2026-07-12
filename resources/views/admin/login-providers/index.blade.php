@extends('layouts.admin')

@section('title', t('Login provider'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.login-providers.index') }}">{{ t('Login provider') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.login-providers.create') }}" class="btn btn-primary" data-testid="login-provider-create-link">
        {{ t('Nuovo provider') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="login-providers-status">
            @switch(session('status'))
                @case('login-provider-created')
                    {{ t('Provider creato correttamente.') }}
                    @break
                @case('login-provider-updated')
                    {{ t('Provider aggiornato correttamente.') }}
                    @break
                @case('login-provider-deleted')
                    {{ t('Provider eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" data-testid="login-providers-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div id="login-providers-grid" data-testid="login-providers-grid"></div>
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
                    { id: 'type', index: 'type', text: @json(t('Tipo')), sortable: true, filterable: true },
                    { id: 'is_active', index: 'is_active', text: @json(t('Attivo')), type: 'boolean' },
                    { id: 'is_system', index: 'is_system', text: @json(t('Sistema')), type: 'boolean' },
                    { id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true, filterable: false },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.login-providers.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.login-providers.index')) + '/' + id;
                            var html = '';
                            if (!params.item.is_system) {
                                html += '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>';
                                html += '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina')) + '</button>' +
                                '</form>';
                            }
                            return html;
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('admin.login-providers.data')),
                    method: 'GET',
                },
            }).render('#login-providers-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
