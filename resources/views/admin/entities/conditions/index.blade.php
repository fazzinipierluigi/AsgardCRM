@extends('layouts.admin')

@section('title', t('Campi condizionali — :entity', ['entity' => $entity->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.builder.edit', $entity) }}">{{ t('Progetta :entity', ['entity' => $entity->name]) }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ t('Campi condizionali') }}
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.entities.conditions.create', $entity) }}" class="btn btn-primary" data-testid="entity-condition-create-link">
        {{ t('Nuova condizione') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="entity-conditions-status">
            @switch(session('status'))
                @case('entity-condition-added')
                    {{ t('Condizione creata correttamente.') }}
                    @break
                @case('entity-condition-updated')
                    {{ t('Condizione aggiornata correttamente.') }}
                    @break
                @case('entity-condition-deleted')
                    {{ t('Condizione eliminata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table" data-testid="entity-conditions-table">
                <thead>
                    <tr>
                        <th>{{ t('Nome') }}</th>
                        <th>{{ t('Campi gestiti') }}</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($conditions as $condition)
                        <tr>
                            <td>{{ $condition->name }}</td>
                            <td>{{ $condition->targets->count() }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.entities.conditions.edit', [$entity, $condition]) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                                    <form method="POST" action="{{ route('admin.entities.conditions.destroy', [$entity, $condition]) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ t('Elimina') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary">{{ t('Nessuna condizione configurata per questa entità.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
