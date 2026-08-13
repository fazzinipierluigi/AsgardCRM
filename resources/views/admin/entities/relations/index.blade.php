@extends('layouts.admin')

@section('title', t('Relazioni N:M — :entity', ['entity' => $entity->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.builder.edit', $entity) }}">{{ t('Progetta :entity', ['entity' => $entity->name]) }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ t('Relazioni N:M') }}
    </li>
@endsection

@section('buttons')
    <a href="{{ route('admin.entities.relations.create', $entity) }}" class="btn btn-primary" data-testid="entity-relation-create-link">
        {{ t('Nuova relazione') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="entity-relations-status">
            @switch(session('status'))
                @case('entity-relation-added')
                    {{ t('Relazione creata correttamente.') }}
                    @break
                @case('entity-relation-updated')
                    {{ t('Relazione aggiornata correttamente.') }}
                    @break
                @case('entity-relation-deleted')
                    {{ t('Relazione eliminata correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table" data-testid="entity-relations-table">
                <thead>
                    <tr>
                        <th>{{ t('Nome') }}</th>
                        <th>{{ t('Entità collegata') }}</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($relations as $relation)
                        <tr>
                            <td>{{ $relation->name }}</td>
                            <td>{{ $relation->otherEntity($entity)->name }}</td>
                            <td class="text-end">
                                <div class="btn-list flex-nowrap">
                                    <a href="{{ route('admin.entities.relations.edit', [$entity, $relation]) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                                    <form method="POST" action="{{ route('admin.entities.relations.destroy', [$entity, $relation]) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ t('Elimina') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-secondary">{{ t('Nessuna relazione configurata per questa entità.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
