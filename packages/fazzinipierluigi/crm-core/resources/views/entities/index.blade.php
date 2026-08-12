@extends('layouts.base')

@section('title', $entity->name)

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('entities.index', $entity) }}">{{ $entity->name }}</a>
    </li>
@endsection

@section('raccoon-layouts')
    @raccoonLayoutsDropdown
@endsection

@if ($canCreate || $buttonWidgets->isNotEmpty())
    @section('buttons')
        @foreach ($buttonWidgets as $widget)
            <button
                type="button"
                class="btn btn-outline-primary"
                data-entity-button
                data-mode="{{ $widget->config['button_action'] ?? '' }}"
                data-url="{{ route('entities.widgets.trigger', [$entity, $widget]) }}"
                data-js="{{ $widget->config['button_javascript'] ?? '' }}"
                data-testid="entity-list-widget-button-{{ $widget->id }}"
            >{{ $widget->name }}</button>
        @endforeach

        @if ($canCreate)
            <a href="{{ route('entities.create', $entity) }}" class="btn btn-primary" data-testid="entity-record-create-link">
                {{ t('Nuovo record') }}
            </a>
        @endif
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

    @if ($displayWidgets->isNotEmpty())
        <div class="row row-cards mb-3">
            @foreach ($displayWidgets as $widget)
                <div class="col-md-{{ $widget->type === 'counter' ? 3 : 6 }}">
                    @if ($widget->type === 'counter')
                        <div class="card" data-counter-widget data-url="{{ route('entities.widgets.data', [$entity, $widget]) }}" data-testid="entity-list-widget-counter-{{ $widget->id }}">
                            <div class="card-body">
                                <div class="subheader">{{ $widget->name }}</div>
                                <div class="h1 mb-0" data-counter-value>—</div>
                            </div>
                        </div>
                    @else
                        <div class="card" data-chart-widget data-url="{{ route('entities.widgets.data', [$entity, $widget]) }}" data-testid="entity-list-widget-chart-{{ $widget->id }}">
                            <div class="card-body">
                                <div class="subheader mb-2">{{ $widget->name }}</div>
                                <canvas data-chart-canvas></canvas>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div id="entity-records-grid" data-testid="entity-records-grid"></div>
    </div>

    @php
        $fieldColumnsData = $entity->allFields()->reject(fn ($f) => $f->type->isAction())->map(function ($f) use ($relationLookups) {
            $isRelation = $f->type->value === 'relation';
            $column = $isRelation ? "{$f->column_name}_id" : $f->column_name;

            $type = match ($f->type->value) {
                'checkbox' => 'boolean',
                'integer', 'decimal' => 'number',
                default => null,
            };

            $filterLookup = match (true) {
                $isRelation => ['options' => $relationLookups[$column] ?? []],
                $f->type->value === 'select' => ['options' => collect($f->options ?? [])->map(fn ($name, $value) => ['value' => $value, 'name' => $name])->values()->all()],
                default => null,
            };

            return array_filter([
                'id' => $column,
                'text' => $f->name,
                'type' => $type,
                'filterLookup' => $filterLookup,
            ], fn ($v) => $v !== null);
        })->values();
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var fieldColumns = @json($fieldColumnsData);

            var columns = [{ id: 'id', index: 'id', text: 'ID', sortable: true, type: 'number' }];

            fieldColumns.forEach(function (f) {
                f.index = f.id;
                columns.push(f);
            });

            columns.push({ id: 'owner', index: 'owner', text: @json(t('Proprietario')), filterable: false });
            columns.push({ id: 'created_at', index: 'created_at', text: @json(t('Creato il')), sortable: true, filterable: false });
            columns.push({
                id: 'actions',
                index: 'id',
                text: @json(t('Azioni')),
                sortable: false,
                filterable: false,
                render: function (params) {
                    var id = params.value;
                    var viewUrl = @json(route('entities.index', $entity)) + '/' + id;
                    var editUrl = @json(route('entities.index', $entity)) + '/' + id + '/edit';
                    var deleteUrl = @json(route('entities.index', $entity)) + '/' + id;
                    var html = '';
                    html += '<a href="' + viewUrl + '" class="btn btn-sm btn-outline-secondary me-1">' + @json(t('Visualizza')) + '</a>';
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

            var grid = new window.RaccoonGrid({
                theme: 'tabler',
                dark: @json(auth()->user()->getSetting('theme', config('preferences.theme.default')) === 'dark'),
                pagination: { enabled: true, pageSize: 25 },
                searchBar: false,
                filterBar: true,
                columns: columns,
                serverAdapter: {
                    url: @json(route('entities.data', $entity)),
                    method: 'GET',
                },
            }).render('#entity-records-grid');

            window.wireRaccoonLayouts(grid);
        });
    </script>
    @raccoonLayoutsScripts
@endsection
