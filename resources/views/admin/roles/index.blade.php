@extends('layouts.admin')

@section('title', t('Ruoli'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.index') }}">{{ t('Ruoli') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.roles.create') }}" class="btn btn-primary" data-testid="role-create-link">
        {{ t('Nuovo ruolo') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="roles-status">
            @switch(session('status'))
                @case('role-created')
                    {{ t('Ruolo creato correttamente.') }}
                    @break
                @case('role-updated')
                    {{ t('Ruolo aggiornato correttamente.') }}
                    @break
                @case('role-deleted')
                    {{ t('Ruolo eliminato correttamente.') }}
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
            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('crm.preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                filterBar: true,
                columns: [
                    { id: 'name', index: 'name', text: @json(t('Nome')), sortable: true, filterable: true },
                    { id: 'slug', index: 'slug', text: @json(t('Slug')), sortable: true, filterable: true },
                    { id: 'permissions_count', index: 'permissions_count', text: @json(t('Permessi')), type: 'number', sortable: false, filterable: false },
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
                            var editUrl = @json(route('admin.roles.index')) + '/' + id + '/edit';
                            var permissionsUrl = @json(route('admin.roles.index')) + '/' + id + '/permissions';
                            var deleteUrl = @json(route('admin.roles.index')) + '/' + id;
                            var html = '';
                            if (!params.item.is_admin) {
                                html += '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>';
                                html += '<a href="' + permissionsUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Permessi')) + '</a>';
                            }
                            if (!params.item.is_system) {
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
                    url: @json(route('admin.roles.data')),
                    method: 'GET',
                },
            }).render('#roles-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
