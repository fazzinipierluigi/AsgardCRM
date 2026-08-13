@extends('layouts.base')

@section('title', t('Le mie caselle e-mail'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('mail.accounts.index') }}">{{ t('Le mie caselle e-mail') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('mail.accounts.create') }}" class="btn btn-primary" data-testid="mail-account-create-link">
        {{ t('Nuova casella') }}
    </a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="mail-accounts-status">
            @switch(session('status'))
                @case('mail-account-created')
                    {{ t('Casella creata correttamente.') }}
                    @break
                @case('mail-account-updated')
                    {{ t('Casella aggiornata correttamente.') }}
                    @break
                @case('mail-account-deleted')
                    {{ t('Casella eliminata correttamente.') }}
                    @break
                @case('mail-account-oauth-connected')
                    {{ t('Casella connessa correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    @error('auth_method')
        <div class="alert alert-danger" data-testid="mail-accounts-oauth-error">{{ $message }}</div>
    @enderror

    <div class="card">
        <div class="list-group list-group-flush" data-testid="mail-accounts-list">
            @forelse ($accounts as $account)
                <div class="list-group-item d-flex justify-content-between align-items-center" data-testid="mail-account-row-{{ $account->id }}">
                    <div>
                        <div class="fw-medium">{{ $account->name }}</div>
                        <div class="text-secondary small">{{ $account->email_address }} &middot; {{ $account->protocol->label() }}</div>
                    </div>
                    <div class="btn-list">
                        @unless ($account->is_active)
                            <span class="badge bg-secondary-lt">{{ t('Disattivata') }}</span>
                        @endunless
                        <a href="{{ route('mail.accounts.edit', $account) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                        <form method="POST" action="{{ route('mail.accounts.destroy', $account) }}" onsubmit="return confirm('{{ t('Confermi l\'eliminazione?') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ t('Elimina') }}</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-secondary" data-testid="mail-accounts-empty">{{ t('Nessuna casella e-mail configurata.') }}</div>
            @endforelse
        </div>
    </div>
@endsection
