@extends('layouts.admin')

@section('title', t('Traduzioni'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.translations.index') }}">{{ t('Traduzioni') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.languages.index') }}" class="btn btn-outline-secondary me-2" data-testid="languages-link">
        {{ t('Gestisci lingue') }}
    </a>
    <a href="{{ route('admin.translations.create') }}" class="btn btn-primary" data-testid="translation-create-link">
        {{ t('Nuova traduzione') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="translations-status">
            @switch(session('status'))
                @case('translation-created')
                    {{ t('Traduzione creata correttamente.') }}
                    @break
                @case('translation-updated')
                    {{ t('Traduzione aggiornata correttamente.') }}
                    @break
                @case('translation-deleted')
                    {{ t('Traduzione eliminata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="translations-grid" data-testid="translations-grid"></div>
    </div>

    @php
        $languageColumnsData = $languages->map(function ($language) {
            return [
                'id' => $language->code,
                'index' => $language->code,
                'text' => $language->name,
                'sortable' => false,
                'filterable' => false,
            ];
        })->values();
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var languageColumns = @json($languageColumnsData);

            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                filterBar: true,
                columns: [
                    { id: 'key', index: 'key', text: @json(t('Chiave')), sortable: true, filterable: true },
                ].concat(languageColumns).concat([
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.translations.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.translations.index')) + '/' + id;
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
                ]),
                serverAdapter: {
                    url: @json(route('admin.translations.data')),
                    method: 'GET',
                },
            }).render('#translations-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
