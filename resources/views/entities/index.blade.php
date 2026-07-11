@extends('layouts.base')

@section('title', $entity->name)

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('entities.index', $entity) }}">{{ $entity->name }}</a>
    </li>
@endsection

@if ($canCreate)
    @section('buttons')
        <a href="{{ route('entities.create', $entity) }}" class="btn btn-primary" data-testid="entity-record-create-link">
            {{ t('Nuovo record') }}
        </a>
    @endsection
@endif

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="entity-records-status">
            @switch(session('status'))
                @case('record-created')
                    {{ t('Record creato correttamente.') }}
                    @break
                @case('record-updated')
                    {{ t('Record aggiornato correttamente.') }}
                    @break
                @case('record-deleted')
                    {{ t('Record eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div id="entity-records-grid" data-testid="entity-records-grid"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var fieldColumns = @json($entity->allFields()->map(function ($f) {
                return [
                    'id' => $f->type->value === 'relation' ? "{$f->column_name}_id" : $f->column_name,
                    'text' => $f->name,
                ];
            })->values());

            var columns = [{ id: 'id', index: 'id', text: 'ID', sortable: true }];

            fieldColumns.forEach(function (f) {
                columns.push({ id: f.id, index: f.id, text: f.text });
            });

            columns.push({ id: 'owner', index: 'owner', text: @json(t('Proprietario')) });
            columns.push({ id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true });
            columns.push({
                id: 'actions',
                index: 'id',
                text: @json(t('Azioni')),
                sortable: false,
                filterable: false,
                render: function (params) {
                    var id = params.value;
                    var editUrl = @json(route('entities.index', $entity)) + '/' + id + '/edit';
                    var deleteUrl = @json(route('entities.index', $entity)) + '/' + id;
                    var html = '';
                    if (params.item.can_edit) {
                        html += '<a href="' + editUrl + '" class="btn btn-sm btn-outline-primary me-1">' + @json(t('Modifica')) + '</a>';
                    }
                    if (params.item.can_delete) {
                        html += '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione?'))) + ');">' +
                            '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                            '<input type="hidden" name="_method" value="DELETE">' +
                            '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina')) + '</button>' +
                        '</form>';
                    }
                    return html;
                },
            });

            new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: false,
                columns: columns,
                serverAdapter: {
                    url: @json(route('entities.data', $entity)),
                    method: 'GET',
                },
            }).render('#entity-records-grid');
        });
    </script>
@endsection
