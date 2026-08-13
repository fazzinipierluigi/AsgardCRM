@extends('layouts.base')

@section('title', t('Modifica record'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('entities.index', $entity) }}">{{ $entity->name }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('entities.edit', [$entity, $record]) }}">{{ t('Modifica record') }}</a>
    </li>
@endsection

@section('buttons')
    @if ($entity->slug === 'ticket')
        @include('crm::entities._ticket-timer-buttons', ['record' => $record])
    @endif
    <a href="{{ route('entities.show', [$entity, $record]) }}" class="btn btn-outline-secondary" data-testid="entity-record-view-link">{{ t('Visualizza') }}</a>
    <button type="submit" form="entity-record-form" class="btn btn-primary" data-testid="entity-record-submit">{{ t('Salva modifiche') }}</button>
@endsection

@section('content')
    @include('crm::entities._record', ['mode' => 'edit'])
@endsection
