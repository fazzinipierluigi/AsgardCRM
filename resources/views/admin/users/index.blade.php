@extends('layouts.admin')

@section('title', __('Utenti'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.users.index') }}">{{ __('Utenti') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary" data-testid="user-create-link">
        {{ __('Nuovo utente') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="users-status">
            @switch(session('status'))
                @case('user-created')
                    {{ __('Utente creato correttamente.') }}
                    @break
                @case('user-updated')
                    {{ __('Utente aggiornato correttamente.') }}
                    @break
                @case('user-deleted')
                    {{ __('Utente eliminato correttamente.') }}
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
            new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                columns: [
                    { id: 'name', index: 'name', text: @json(__('Nome')), sortable: true, filterable: true },
                    { id: 'username', index: 'username', text: @json(__('Username')), sortable: true, filterable: true },
                    { id: 'email', index: 'email', text: @json(__('Email')), sortable: true, filterable: true },
                    { id: 'roles', index: 'roles', text: @json(__('Ruoli')) },
                    { id: 'created_at', index: 'created_at', text: @json(__('Creato il')), sortable: true },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(__('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.users.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.users.index')) + '/' + id;
                            return (
                                '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(__('Modifica')) + '</a>' +
                                '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(__('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(__('Elimina')) + '</button>' +
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
        });
    </script>
@endsection
