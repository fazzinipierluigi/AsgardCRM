@extends('layouts.base')

@section('title', t('Visualizza record'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('entities.index', $entity) }}">{{ $entity->name }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('entities.show', [$entity, $record]) }}">{{ t('Visualizza record') }}</a>
    </li>
@endsection

@section('buttons')
    @if ($entity->slug === 'ticket')
        @include('crm::entities._ticket-timer-buttons', ['record' => $record])
    @endif
    @if ($canEdit ?? false)
        <a href="{{ route('entities.edit', [$entity, $record]) }}" class="btn btn-primary" data-testid="entity-record-edit-link">{{ t('Modifica') }}</a>
    @endif
@endsection

@section('content')
    @include('crm::entities._record', ['mode' => 'view'])
@endsection
