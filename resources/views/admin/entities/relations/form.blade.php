@php
    $relation = $relation ?? null;
    $isEdit = $relation !== null;
@endphp
@extends('layouts.admin')

@section('title', $isEdit ? t('Modifica relazione') : t('Nuova relazione'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.relations.index', $entity) }}">{{ t('Relazioni N:M') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        {{ $isEdit ? t('Modifica relazione') : t('Nuova relazione') }}
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-relation-form" class="btn btn-primary" data-testid="entity-relation-submit">{{ t('Salva') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form
                action="{{ $isEdit ? route('admin.entities.relations.update', [$entity, $relation]) : route('admin.entities.relations.store', $entity) }}"
                method="POST"
                id="entity-relation-form"
            >
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">{{ t('Nome relazione') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $relation?->name) }}" class="form-control @error('name') is-invalid @enderror" data-testid="entity-relation-name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="entity_b_id" class="form-label">{{ t('Entità collegata') }}</label>
                        <select id="entity_b_id" name="entity_b_id" class="form-select @error('entity_b_id') is-invalid @enderror" data-testid="entity-relation-target">
                            <option value="">{{ t('Seleziona...') }}</option>
                            @foreach ($otherEntities as $other)
                                <option value="{{ $other->id }}" @selected((string) old('entity_b_id', $otherEntityId ?? null) === (string) $other->id)>{{ $other->name }}</option>
                            @endforeach
                        </select>
                        @error('entity_b_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
