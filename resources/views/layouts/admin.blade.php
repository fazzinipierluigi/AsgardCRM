@extends('layouts.app')

@section('menu')
    <ul class="navbar-nav pt-lg-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" data-testid="menu-users">
                <span class="nav-link-title">{{ t('Utenti') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}" data-testid="menu-roles">
                <span class="nav-link-title">{{ t('Ruoli') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.translations.*') ? 'active' : '' }}" href="{{ route('admin.translations.index') }}" data-testid="menu-translations">
                <span class="nav-link-title">{{ t('Traduzioni') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.languages.*') ? 'active' : '' }}" href="{{ route('admin.languages.index') }}" data-testid="menu-languages">
                <span class="nav-link-title">{{ t('Lingue') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.entities.*') ? 'active' : '' }}" href="{{ route('admin.entities.index') }}" data-testid="menu-entities">
                <span class="nav-link-title">{{ t('Entità') }}</span>
            </a>
        </li>
    </ul>

    <div class="mt-auto">
        <ul class="navbar-nav pb-lg-3">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('dashboard') }}" data-testid="menu-back-to-dashboard">
                    <span class="nav-link-title">{{ t('← Torna alla Dashboard') }}</span>
                </a>
            </li>
        </ul>
    </div>
@endsection
