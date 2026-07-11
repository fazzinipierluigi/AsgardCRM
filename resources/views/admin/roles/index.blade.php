@extends('layouts.admin')

@section('title', __('Ruoli'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.index') }}">{{ __('Ruoli') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary" data-testid="role-create-link">
        {{ __('Nuovo ruolo') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="roles-status">
            @switch(session('status'))
                @case('role-created')
                    {{ __('Ruolo creato correttamente.') }}
                    @break
                @case('role-updated')
                    {{ __('Ruolo aggiornato correttamente.') }}
                    @break
                @case('role-deleted')
                    {{ __('Ruolo eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" data-testid="roles-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div id="roles-grid" data-testid="roles-grid"></div>
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
                    { id: 'slug', index: 'slug', text: @json(__('Slug')), sortable: true, filterable: true },
                    { id: 'permissions_count', index: 'permissions_count', text: @json(__('Permessi')), type: 'number' },
                    {
                        id: 'is_system',
                        index: 'is_system',
                        text: @json(__('Sistema')),
                        render: function (params) {
                            return params.value ? @json(__('Sì')) : @json(__('No'));
                        },
                    },
                    { id: 'created_at', index: 'created_at', text: @json(__('Creato il')), sortable: true },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(__('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.roles.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.roles.index')) + '/' + id;
                            var html = '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(__('Modifica')) + '</a>';
                            if (!params.item.is_system) {
                                html += '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(__('Confermi l\'eliminazione?'))) + ');">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(__('Elimina')) + '</button>' +
                                '</form>';
                            }
                            return html;
                        },
                    },
                ],
                serverAdapter: {
                    url: @json(route('admin.roles.data')),
                    method: 'GET',
                },
            }).render('#roles-grid');
        });
    </script>
@endsection
