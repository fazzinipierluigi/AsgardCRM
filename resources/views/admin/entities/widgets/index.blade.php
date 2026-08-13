@extends('layouts.admin')

@section('title', t('Widget lista — :entity', ['entity' => $entity->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.builder.edit', $entity) }}">{{ t('Progetta :entity', ['entity' => $entity->name]) }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ t('Widget lista') }}
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.entities.widgets.create', $entity) }}" class="btn btn-primary" data-testid="entity-widget-create-link">
        {{ t('Nuovo widget') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="entity-widgets-status">
            @switch(session('status'))
                @case('entity-widget-added')
                    {{ t('Widget creato correttamente.') }}
                    @break
                @case('entity-widget-updated')
                    {{ t('Widget aggiornato correttamente.') }}
                    @break
                @case('entity-widget-deleted')
                    {{ t('Widget eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table" data-testid="entity-widgets-table">
                <thead>
                    <tr>
                        <th>{{ t('Nome') }}</th>
                        <th>{{ t('Tipo') }}</th>
                        <th>{{ t('Posizione') }}</th>
                        <th>{{ t('Attivo') }}</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entity->listWidgets as $widget)
                        <tr>
                            <td>{{ $widget->name }}</td>
                            <td>
                                @switch($widget->type)
                                    @case('button')
                                        {{ t('Bottone') }}
                                        @break
                                    @case('counter')
                                        {{ t('Contatore') }}
                                        @break
                                    @case('chart')
                                        {{ t('Grafico') }}
                                        @break
                                @endswitch
                            </td>
                            <td>{{ $widget->position }}</td>
                            <td>{{ $widget->is_active ? t('Sì') : t('No') }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.entities.widgets.edit', [$entity, $widget]) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                                    <form method="POST" action="{{ route('admin.entities.widgets.destroy', [$entity, $widget]) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ t('Elimina') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-secondary">{{ t('Nessun widget configurato per questa entità.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
