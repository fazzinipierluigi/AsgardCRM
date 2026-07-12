@extends('layouts.app')

@section('menu')
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}" data-testid="menu-dashboard">
                <span class="nav-link-icon">
                    {!! icon('gauge') !!}
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
                            <span class="nav-link-icon">{!! icon($installedEntity->icon) !!}</span>
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
                            {!! icon('settings-cog') !!}
                        </span>
                        <span class="nav-link-title">{{ t('Amministrazione') }}</span>
                    </a>
                </li>
            </ul>
        @endcan
    </div>
@endsection
