@extends('layouts.admin')

@section('title', t('Utenti'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.users.index') }}">{{ t('Utenti') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary" data-testid="user-create-link">
        {{ t('Nuovo utente') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="users-status">
            @switch(session('status'))
                @case('user-created')
                    {{ t('Utente creato correttamente.') }}
                    @break
                @case('user-updated')
                    {{ t('Utente aggiornato correttamente.') }}
                    @break
                @case('user-deleted')
                    {{ t('Utente eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" data-testid="users-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div id="users-grid" data-testid="users-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('crm.preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                filterBar: true,
                columns: [
                    { id: 'name', index: 'name', text: @json(t('Nome')), sortable: true, filterable: true },
                    { id: 'username', index: 'username', text: @json(t('Username')), sortable: true, filterable: true },
                    { id: 'email', index: 'email', text: @json(t('Email')), sortable: true, filterable: true },
                    { id: 'roles', index: 'roles', text: @json(t('Ruoli')), sortable: false, filterable: false },
                    { id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true, filterable: false },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.users.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.users.index')) + '/' + id;
                            return (
                                '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>' +
                                '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina')) + '</button>' +
                                '</form>'
                            );
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('admin.users.data')),
                    method: 'GET',
                },
            }).render('#users-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
