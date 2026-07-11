@extends('layouts.admin')

@section('title', t('Entità'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.entities.import.form') }}" class="btn btn-outline-secondary" data-testid="entity-import-link">
        {{ t('Importa') }}
    </a>
    <a href="{{ route('admin.entities.create') }}" class="btn btn-primary" data-testid="entity-create-link">
        {{ t('Nuova entità') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="entities-status">
            @switch(session('status'))
                @case('entity-created')
                    {{ t('Entità creata correttamente.') }}
                    @break
                @case('entity-updated')
                    {{ t('Entità aggiornata correttamente.') }}
                    @break
                @case('entity-deleted')
                    {{ t('Entità eliminata correttamente.') }}
                    @break
                @case('entity-installed')
                    {{ t('Entità installata correttamente.') }}
                    @break
                @case('entity-uninstalled')
                    {{ t('Entità disinstallata correttamente.') }}
                    @break
                @case('entity-visibility-updated')
                    {{ t('Visibilità aggiornata correttamente.') }}
                    @break
                @case('entity-imported')
                    {{ t('Entità importata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" data-testid="entities-error">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div id="entities-grid" data-testid="entities-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                columns: [
                    { id: 'name', index: 'name', text: @json(t('Nome')), sortable: true, filterable: true },
                    { id: 'slug', index: 'slug', text: @json(t('Slug')), sortable: true, filterable: true },
                    {
                        id: 'is_system',
                        index: 'is_system',
                        text: @json(t('Sistema')),
                        render: function (params) {
                            return params.value ? @json(t('Sì')) : @json(t('No'));
                        },
                    },
                    {
                        id: 'is_installed',
                        index: 'is_installed',
                        text: @json(t('Installata')),
                        render: function (params) {
                            return params.value ? @json(t('Sì')) : @json(t('No'));
                        },
                    },
                    { id: 'created_at', index: 'created_at', text: @json(t('Creata il')), sortable: true },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.entities.index')) + '/' + id + '/edit';
                            var builderUrl = @json(route('admin.entities.index')) + '/' + id + '/builder';
                            var deleteUrl = @json(route('admin.entities.index')) + '/' + id;
                            var installUrl = @json(route('admin.entities.index')) + '/' + id + '/install';
                            var uninstallUrl = @json(route('admin.entities.index')) + '/' + id + '/uninstall';
                            var visibilityUrl = @json(route('admin.entities.index')) + '/' + id + '/visibility';
                            var exportUrl = @json(route('admin.entities.index')) + '/' + id + '/export';
                            var html = '';
                            html += '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>';
                            html += '<a href="' + builderUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Progetta')) + '</a>';
                            html += '<a href="' + visibilityUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Visibilità')) + '</a>';
                            html += '<a href="' + exportUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Esporta')) + '</a>';
                            if (params.item.is_installed) {
                                if (!params.item.is_system) {
                                    html += '<form method="POST" action="' + uninstallUrl + '" style="display:inline" class="me-1">' +
                                        '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                        '<button type="submit" class="btn btn-sm btn-outline-warning">' + @json(t('Disinstalla')) + '</button>' +
                                    '</form>';
                                }
                            } else {
                                html += '<form method="POST" action="' + installUrl + '" style="display:inline" class="me-1">' +
                                    '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                    '<button type="submit" class="btn btn-sm btn-outline-success">' + @json(t('Installa')) + '</button>' +
                                '</form>';
                            }
                            if (!params.item.is_system && !params.item.is_installed) {
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
                    url: @json(route('admin.entities.data')),
                    method: 'GET',
                },
            }).render('#entities-grid');
        });
    </script>
@endsection
