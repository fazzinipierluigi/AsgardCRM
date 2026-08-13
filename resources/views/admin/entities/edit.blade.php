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

@section('buttons')
    <a href="{{ route('admin.entities.builder.edit', $entity) }}" class="btn btn-link" data-testid="entity-manage-builder-link">
        {{ t('Progetta struttura') }}
    </a>
    <button type="submit" form="entity-form" class="btn btn-primary" data-testid="entity-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.entities.update', $entity) }}" method="POST" id="entity-form">
                @csrf
                @method('PUT')
                @include('crm::admin.entities._form', ['entity' => $entity])
            </form>
        </div>
    </div>
@endsection
