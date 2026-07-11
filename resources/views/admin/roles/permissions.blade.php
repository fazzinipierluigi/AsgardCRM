@extends('layouts.admin')

@section('title', t('Permessi di :role', ['role' => $role->name]))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('admin.roles.index') }}">{{ t('Ruoli') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.roles.permissions.edit', $role) }}">{{ t('Permessi di :role', ['role' => $role->name]) }}</a>
    </li>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            @if ($role->is_admin)
                <div class="alert alert-info" data-testid="role-permissions-admin-notice">
                    {{ t('Questo ruolo ha accesso completo automatico: i permessi selezionati qui non hanno effetto.') }}
                </div>
            @endif

            <form action="{{ route('admin.roles.permissions.update', $role) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    @forelse ($permissions as $group => $groupPermissions)
                        <div class="col-12 col-lg-4 mb-3">
                            <div class="fw-bold text-uppercase small text-muted">{{ $group }}</div>
                            @foreach ($groupPermissions as $permission)
                                <label class="form-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="permissions[]"
                                        value="{{ $permission->key }}"
                                        @checked(in_array($permission->key, old('permissions', $rolePermissionKeys)))
                                    >
                                    <span class="form-check-label">{{ $permission->name ?? $permission->key }}</span>
                                </label>
                            @endforeach
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-muted">{{ t('Nessun permesso disponibile.') }}</div>
                        </div>
                    @endforelse
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary" data-testid="role-permissions-submit">{{ t('Salva permessi') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
