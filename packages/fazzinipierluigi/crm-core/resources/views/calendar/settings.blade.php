@extends('layouts.base')

@section('title', t('Impostazioni calendario'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('calendar.index') }}">{{ t('Calendario') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('calendar.settings.edit') }}">{{ t('Impostazioni calendario') }}</a>
    </li>
@endsection

@if ($shareableUsers->isNotEmpty())
    @section('buttons')
        <button type="submit" form="calendar-shares-form" class="btn btn-primary" data-testid="calendar-shares-submit">{{ t('Salva') }}</button>
    @endsection
@endif

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ t('Condivisioni attive') }}</h3>
        </div>
        <div class="card-body">
            @if (session('status') === 'calendar-shares-updated')
                <div class="alert alert-success" data-testid="calendar-shares-status">{{ t('Impostazioni aggiornate.') }}</div>
            @endif

            @if ($shareableUsers->isEmpty())
                <div class="text-muted" data-testid="calendar-shares-no-users">{{ t('Nessun ruolo configurabile.') }}</div>
            @else
                <form action="{{ route('calendar.settings.shares.update') }}" method="POST" id="calendar-shares-form">
                    @csrf
                    @method('PUT')

                    <table class="table" data-testid="calendar-shares-table">
                        <thead>
                            <tr>
                                <th>{{ t('Condividi con') }}</th>
                                <th>{{ t('Nessuna') }}</th>
                                @foreach (\Fazzinipierluigi\CrmCore\Enums\CalendarSharePermission::options() as $value => $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shareableUsers as $shareUser)
                                @php
                                    $currentPermission = old("shares.{$shareUser->id}", $currentShares[$shareUser->id] ?? 'none');
                                @endphp
                                <tr data-testid="calendar-share-row-{{ $shareUser->id }}">
                                    <td>{{ $shareUser->name }}</td>
                                    <td>
                                        <input type="radio" class="form-check-input" name="shares[{{ $shareUser->id }}]" value="none" @checked($currentPermission === 'none')>
                                    </td>
                                    @foreach (\Fazzinipierluigi\CrmCore\Enums\CalendarSharePermission::options() as $value => $label)
                                        <td>
                                            <input type="radio" class="form-check-input" name="shares[{{ $shareUser->id }}]" value="{{ $value }}" @checked($currentPermission === $value)>
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
