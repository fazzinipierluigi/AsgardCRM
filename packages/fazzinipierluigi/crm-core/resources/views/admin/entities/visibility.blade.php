@extends('layouts.admin')

@section('title', t('Visibilità di :entity', ['entity' => $entity->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.entities.visibility.edit', $entity) }}">{{ t('Visibilità di :entity', ['entity' => $entity->name]) }}</a>
    </li>
@endsection

@if ($roles->isNotEmpty())
    @section('buttons')
        <button type="submit" form="entity-visibility-form" class="btn btn-primary" data-testid="entity-visibility-submit">{{ t('Salva visibilità') }}</button>
    @endsection
@endif

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($roles->isEmpty())
                <div class="text-muted" data-testid="entity-visibility-no-roles">{{ t('Nessun ruolo configurabile.') }}</div>
            @else
                <form action="{{ route('admin.entities.visibility.update', $entity) }}" method="POST" id="entity-visibility-form">
                    @csrf
                    @method('PUT')

                    <table class="table" data-testid="entity-visibility-table">
                        <thead>
                            <tr>
                                <th>{{ t('Ruolo') }}</th>
                                @foreach (\Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel::options() as $value => $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                @php
                                    $currentLevel = old("levels.{$role->id}", $currentLevels[$role->id] ?? \Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel::OwnOnly->value);
                                @endphp
                                <tr data-testid="entity-visibility-row-{{ $role->slug }}">
                                    <td>{{ $role->name }}</td>
                                    @foreach (\Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel::options() as $value => $label)
                                        <td>
                                            <input
                                                type="radio"
                                                class="form-check-input"
                                                name="levels[{{ $role->id }}]"
                                                value="{{ $value }}"
                                                @checked($currentLevel === $value)
                                            >
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </form>
            @endif
        </div>
    </div>
@endsection
