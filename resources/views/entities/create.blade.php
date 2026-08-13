@extends('layouts.base')

@section('title', t('Nuovo record'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('entities.index', $entity) }}">{{ $entity->name }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('entities.create', $entity) }}">{{ t('Nuovo record') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="entity-record-form" class="btn btn-primary" data-testid="entity-record-submit">{{ t('Salva') }}</button>
@endsection

@section('content')
    <form action="{{ route('entities.store', $entity) }}" method="POST" id="entity-record-form">
        @csrf
        @include('crm::entities._form', ['entity' => $entity, 'record' => null, 'relationOptions' => $relationOptions, 'productsBlockOptions' => $productsBlockOptions ?? []])
    </form>

    <script>
        window.ENTITY_FIELD_CONDITIONS = @json($fieldConditions ?? []);
    </script>

    @vite(['resources/js/entity-record-form.js', 'resources/js/entity-field-conditions.js'], 'vendor/crm')
@endsection
