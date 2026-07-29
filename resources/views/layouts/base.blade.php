@extends('layouts.app')

@php
    // The Calendar entity gets its own dedicated FullCalendar UI above
    // (see CalendarController) instead of the generic per-entity grid
    // every other installed entity gets here.
    $installedEntities = \App\Models\Entity::where('is_installed', true)
        ->where('is_calendar', false)
        ->orderBy('menu_position')
        ->orderBy('name')
        ->get();
    $visibleMenuEntities = $installedEntities->where('show_in_menu', true);
    $otherMenuEntities = $installedEntities->where('show_in_menu', false);
    $isInOtherEntities = request()->routeIs('entities.*')
        && $otherMenuEntities->contains('slug', request()->route('entity')?->slug);
@endphp

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

        @can('entity_calendario.index')
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}" href="{{ route('calendar.index') }}" data-testid="menu-calendar">
                    <span class="nav-link-icon">{!! icon('calendar') !!}</span>
                    <span class="nav-link-title">{{ t('Calendario') }}</span>
                </a>
            </li>
        @endcan

        @foreach ($visibleMenuEntities as $installedEntity)
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
        @if ($otherMenuEntities->isNotEmpty())
            <ul class="navbar-nav pb-2">
                <li class="nav-item">
                    <a
                        class="nav-link {{ $isInOtherEntities ? 'active' : '' }}"
                        href="#other-entities-menu"
                        data-bs-toggle="collapse"
                        aria-expanded="{{ $isInOtherEntities ? 'true' : 'false' }}"
                        data-testid="menu-other-entities"
                    >
                        <span class="nav-link-icon">{!! icon('dots') !!}</span>
                        <span class="nav-link-title">{{ t('Altre entità') }}</span>
                    </a>
                    <div class="collapse {{ $isInOtherEntities ? 'show' : '' }}" id="other-entities-menu">
                        <ul class="nav-sub">
                            @foreach ($otherMenuEntities as $otherEntity)
                                @can("entity_{$otherEntity->slug}.index")
                                    <li class="nav-sub-item">
                                        <a
                                            class="nav-sub-link {{ request()->routeIs('entities.*') && request()->route('entity')?->slug === $otherEntity->slug ? 'active' : '' }}"
                                            href="{{ route('entities.index', $otherEntity) }}"
                                            data-testid="menu-entity-{{ $otherEntity->slug }}"
                                        >
                                            @if ($otherEntity->icon)
                                                <span class="nav-link-icon">{!! icon($otherEntity->icon) !!}</span>
                                            @endif
                                            <span class="nav-link-title">{{ $otherEntity->name }}</span>
                                        </a>
                                    </li>
                                @endcan
                            @endforeach
                        </ul>
                    </div>
                </li>
            </ul>
        @endif

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
