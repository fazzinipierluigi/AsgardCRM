@extends('layouts.admin')

@section('title', t('Firme e-mail'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.mail-signatures.index') }}">{{ t('Firme e-mail') }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@section('buttons')
    <a href="{{ route('admin.mail-signatures.create') }}" class="btn btn-primary" data-testid="mail-signature-create-link">
        {{ t('Nuova firma') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="mail-signatures-status">
            @switch(session('status'))
                @case('mail-signature-created')
                    {{ t('Firma creata correttamente.') }}
                    @break
                @case('mail-signature-updated')
                    {{ t('Firma aggiornata correttamente.') }}
                    @break
                @case('mail-signature-deleted')
                    {{ t('Firma eliminata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="mail-signatures-grid" data-testid="mail-signatures-grid"></div>
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
                    { id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true, filterable: false },
                    {
                        id: 'actions',
                        index: 'id',
                        text: @json(t('Azioni')),
                        sortable: false,
                        filterable: false,
                        render: function (params) {
                            var id = params.value;
                            var editUrl = @json(route('admin.mail-signatures.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.mail-signatures.index')) + '/' + id;
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
                    url: @json(route('admin.mail-signatures.data')),
                    method: 'GET',
                },
            }).render('#mail-signatures-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
