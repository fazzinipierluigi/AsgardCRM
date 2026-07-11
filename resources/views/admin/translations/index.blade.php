@extends('layouts.admin')

@section('title', __('Traduzioni'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.translations.index') }}">{{ __('Traduzioni') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.translations.create') }}" class="btn btn-primary" data-testid="translation-create-link">
        {{ __('Nuova traduzione') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="translations-status">
            @switch(session('status'))
                @case('translation-created')
                    {{ __('Traduzione creata correttamente.') }}
                    @break
                @case('translation-updated')
                    {{ __('Traduzione aggiornata correttamente.') }}
                    @break
                @case('translation-deleted')
                    {{ __('Traduzione eliminata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="translations-grid" data-testid="translations-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                columns: [
                    { id: 'key', index: 'key', text: @json(__('Chiave')), sortable: true, filterable: true },
                    { id: 'language', index: 'language', text: @json(__('Lingua')), sortable: true, filterable: true },
                    { id: 'value', index: 'value', text: @json(__('Valore')), sortable: true, filterable: true },
                    { id: 'created_at', index: 'created_at', text: @json(__('Creato il')), sortable: true },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(__('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.translations.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.translations.index')) + '/' + id;
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
                    url: @json(route('admin.translations.data')),
                    method: 'GET',
                },
            }).render('#translations-grid');
        });
    </script>
@endsection
