@extends('layouts.admin')

@section('title', __('Permessi'))

@section('content')
    <div class="page-header d-print-none">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="page-title">{{ __('Permessi') }}</h2>
            </div>
            <div class="col-auto">
                <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary" data-testid="permission-create-link">
                    {{ __('Nuovo permesso') }}
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" data-testid="permissions-status">
            @switch(session('status'))
                @case('permission-created')
                    {{ __('Permesso creato correttamente.') }}
                    @break
                @case('permission-updated')
                    {{ __('Permesso aggiornato correttamente.') }}
                    @break
                @case('permission-deleted')
                    {{ __('Permesso eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="permissions-grid" data-testid="permissions-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            new window.RaccoonGrid({
                theme: 'tabler',
                pagination: { enabled: true, pageSize: 25 },
                searchBar: true,
                columns: [
                    { id: 'key', index: 'key', text: @json(__('Chiave')), sortable: true, filterable: true },
                    { id: 'name', index: 'name', text: @json(__('Nome')), sortable: true, filterable: true },
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
                            var editUrl = @json(route('admin.permissions.index')) + '/' + id + '/edit';
                            var deleteUrl = @json(route('admin.permissions.index')) + '/' + id;
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
                    url: @json(route('admin.permissions.data')),
                    method: 'GET',
                },
            }).render('#permissions-grid');
        });
    </script>
@endsection
