@extends('layouts.app')

@section('menu')
    <ul class="navbar-nav pt-lg-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" data-testid="menu-dashboard">
                <span class="nav-link-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h6v8h-6z" />
                        <path d="M4 16h6v4h-6z" />
                        <path d="M14 12h6v8h-6z" />
                        <path d="M14 4h6v4h-6z" />
                    </svg>
                </span>
                <span class="nav-link-title">{{ t('Dashboard') }}</span>
            </a>
        </li>

        @foreach (\App\Models\Entity::where('is_installed', true)->orderBy('name')->get() as $installedEntity)
            @can("entity_{$installedEntity->slug}.index")
                <li class="nav-item">
                    <a
                        class="nav-link {{ request()->routeIs('entities.*') && request()->route('entity')?->slug === $installedEntity->slug ? 'active' : '' }}"
                        href="{{ route('entities.index', $installedEntity) }}"
                        data-testid="menu-entity-{{ $installedEntity->slug }}"
                    >
                        @if ($installedEntity->icon)
                            <span class="nav-link-icon"><i class="{{ $installedEntity->icon }}"></i></span>
                        @endif
                        <span class="nav-link-title">{{ $installedEntity->name }}</span>
                    </a>
                </li>
            @endcan
        @endforeach
    </ul>

    <div class="mt-auto">
        @can('admin.access')
            <ul class="navbar-nav pb-lg-3">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.users.index') }}" data-testid="menu-admin">
                        <span class="nav-link-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 12a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M6.15 18a6.002 6.002 0 0 1 11.7 0" />
                                <path d="M14 3.5a6 6 0 0 1 4.5 5.5" />
                                <path d="M14 3.5a6 6 0 1 0 -8 5.65" />
                            </svg>
                        </span>
                        <span class="nav-link-title">{{ t('Amministrazione') }}</span>
                    </a>
                </li>
            </ul>
        @endcan
    </div>
@endsection
