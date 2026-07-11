@extends('layouts.app')

@section('menu')
    <ul class="navbar-nav pt-lg-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" data-testid="menu-users">
                <span class="nav-link-title">{{ __('Utenti') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}" data-testid="menu-roles">
                <span class="nav-link-title">{{ __('Ruoli') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" href="{{ route('admin.permissions.index') }}" data-testid="menu-permissions">
                <span class="nav-link-title">{{ __('Permessi') }}</span>
            </a>
        </li>
    </ul>

    <div class="mt-auto">
        <ul class="navbar-nav pb-lg-3">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard') }}" data-testid="menu-back-to-dashboard">
                    <span class="nav-link-title">{{ __('← Torna alla Dashboard') }}</span>
                </a>
            </li>
        </ul>
    </div>
@endsection
