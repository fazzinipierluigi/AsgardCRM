@extends('layouts.admin')

@section('title', t('Modifica entità'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.entities.index') }}">{{ t('Entità') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.entities.edit', $entity) }}">{{ t('Modifica entità') }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.entities.update', $entity) }}" method="POST">
                @csrf
                @method('PUT')
                @include('admin.entities._form', ['entity' => $entity])

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="entity-submit">{{ t('Salva modifiche') }}</button>
                    <a href="{{ route('admin.entities.builder.edit', $entity) }}" class="btn btn-link" data-testid="entity-manage-builder-link">
                        {{ t('Progetta struttura') }}
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
