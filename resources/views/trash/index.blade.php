@extends('layouts.base')

@section('title', t('Cestino'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('trash.index') }}">{{ t('Cestino') }}</a>
    </li>
@endsection

@section('buttons')
    @if ($entity && $canEmpty)
        <button type="button" class="btn btn-outline-danger" id="trash-empty-entity-btn" data-testid="trash-empty-entity-button">
            {{ t('Svuota cestino') }}
        </button>
        <form id="trash-empty-entity-form" method="POST" action="{{ route('trash.empty-entity', $entity) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endif

    @if ($canEmpty)
        <button type="button" class="btn btn-danger" id="trash-empty-all-btn" data-testid="trash-empty-all-button">
            {{ t('Svuota tutto il cestino') }}
        </button>
        <form id="trash-empty-all-form" method="POST" action="{{ route('trash.empty-all') }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="trash-status">
            @switch(session('status'))
                @case('record-restored')
                    {{ t('Record ripristinato correttamente.') }}
                    @break
                @case('record-force-deleted')
                    {{ t('Record eliminato definitivamente.') }}
                    @break
                @case('trash-emptied')
                    {{ t('Cestino svuotato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label">{{ t('Entità') }}</label>
            <select id="trash-entity-select" class="form-select" data-testid="trash-entity-select">
                <option value="">{{ t('Seleziona un\'entità') }}</option>
                @foreach ($entities as $option)
                    <option value="{{ $option->slug }}" @selected($entity && $entity->slug === $option->slug)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($entity)
        <div class="card">
            <div id="trash-records-grid" data-testid="trash-records-grid"></div>
        </div>

        @php
            $fieldColumnsData = $entity->allFields()->reject(fn ($f) => $f->type->isAction())->take(3)->map(function ($f) {
                $isRelation = $f->type->value === 'relation';
                $column = $isRelation ? "{$f->column_name}_id" : $f->column_name;

                return ['id' => $column, 'text' => $f->name];
            })->values();
        @endphp

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var fieldColumns = @json($fieldColumnsData);

                var columns = [{ id: 'id', index: 'id', text: 'ID', sortable: true, type: 'number' }];

                fieldColumns.forEach(function (f) {
                    f.index = f.id;
                    f.filterable = false;
                    columns.push(f);
                });

                columns.push({ id: 'owner', index: 'owner', text: @json(t('Proprietario')), filterable: false });
                columns.push({ id: 'deleted_at', index: 'deleted_at', text: @json(t('Eliminato il')), filterable: false });
                columns.push({
                    id: 'actions',
                    index: 'id',
                    text: @json(t('Azioni')),
                    sortable: false,
                    filterable: false,
                    render: function (params) {
                        var id = params.value;
                        if (!params.item.can_restore) {
                            return '';
                        }
                        var base = @json(url('trash/'.$entity->slug)) + '/' + id;
                        var restoreUrl = base + '/restore';
                        var deleteUrl = base;

                        return '<form method="POST" action="' + restoreUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi il ripristino?'))) + ');">' +
                                '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                '<button type="submit" class="btn btn-sm btn-outline-success me-1">' + @json(t('Ripristina')) + '</button>' +
                            '</form>' +
                            '<form method="POST" action="' + deleteUrl + '" style="display:inline" onsubmit="return confirm(' + JSON.stringify(@json(t('Confermi l\'eliminazione definitiva? L\'azione non è reversibile.'))) + ');">' +
                                '<input type="hidden" name="_token" value="' + window.CSRF_TOKEN + '">' +
                                '<input type="hidden" name="_method" value="DELETE">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger">' + @json(t('Elimina definitivamente')) + '</button>' +
                            '</form>';
                    },
                });

                new window.RaccoonGrid({
                    theme: 'tabler',
                    dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                    pagination: { enabled: true, pageSize: 25 },
                    searchBar: false,
                    filterBar: false,
                    columns: columns,
                    serverAdapter: {
                        url: @json(route('trash.data', $entity)),
                        method: 'GET',
                    },
                }).render('#trash-records-grid');
            });
        </script>
    @endif

    <script>
        document.getElementById('trash-entity-select').addEventListener('change', function () {
            var url = @json(route('trash.index'));
            window.location = this.value ? (url + '?entity=' + encodeURIComponent(this.value)) : url;
        });

        var emptyEntityBtn = document.getElementById('trash-empty-entity-btn');
        if (emptyEntityBtn) {
            emptyEntityBtn.addEventListener('click', function () {
                if (confirm(@json(t('Confermi lo svuotamento del cestino? L\'azione non è reversibile.')))) {
                    document.getElementById('trash-empty-entity-form').submit();
                }
            });
        }

        var emptyAllBtn = document.getElementById('trash-empty-all-btn');
        if (emptyAllBtn) {
            emptyAllBtn.addEventListener('click', function () {
                if (confirm(@json(t('Confermi lo svuotamento del cestino? L\'azione non è reversibile.')))) {
                    document.getElementById('trash-empty-all-form').submit();
                }
            });
        }
    </script>
@endsection
