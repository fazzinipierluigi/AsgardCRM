@extends('layouts.app')

@php
    // System entities (e.g. Calendario) are configurable in the same
    // menu builder as any custom entity — see MenuController — and
    // rendered through this same loop; only the link target differs
    // (entityMenuUrl()/entityMenuIsActive() below), since Calendario
    // gets its own dedicated FullCalendar UI (CalendarController)
    // instead of the generic per-entity grid every other entity uses.
    $installedEntities = \App\Models\Entity::where('is_installed', true)
        ->orderBy('menu_position')
        ->orderBy('name')
        ->get();
    $visibleMenuEntities = $installedEntities->where('show_in_menu', true);
    $otherMenuEntities = $installedEntities->where('show_in_menu', false);
    $entityMenuUrl = fn ($entity) => $entity->is_calendar ? route('calendar.index') : route('entities.index', $entity);
    $entityMenuIsActive = fn ($entity) => $entity->is_calendar
        ? request()->routeIs('calendar.*')
        : (request()->routeIs('entities.*') && request()->route('entity')?->slug === $entity->slug);
    $isInOtherEntities = $otherMenuEntities->contains($entityMenuIsActive);
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

        @foreach ($visibleMenuEntities as $installedEntity)
            @can("entity_{$installedEntity->slug}.index")
                <li class="nav-item">
                    <a
                        class="nav-link {{ $entityMenuIsActive($installedEntity) ? 'active' : '' }}"
                        href="{{ $entityMenuUrl($installedEntity) }}"
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
                                            class="nav-sub-link {{ $entityMenuIsActive($otherEntity) ? 'active' : '' }}"
                                            href="{{ $entityMenuUrl($otherEntity) }}"
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

        @can('trash.show')
            <ul class="navbar-nav pb-lg-3">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('trash.index') }}" data-testid="menu-trash">
                        <span class="nav-link-icon">
                            {!! icon('trash') !!}
                        </span>
                        <span class="nav-link-title">{{ t('Cestino') }}</span>
                    </a>
                </li>
            </ul>
        @endcan

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
