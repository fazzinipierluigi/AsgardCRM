<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-bs-theme="{{ auth()->user()?->getSetting('theme', config('preferences.theme.default')) }}"
    data-bs-theme-base="{{ auth()->user()?->getSetting('theme_base', config('preferences.theme_base.default')) }}"
    data-bs-theme-primary="{{ auth()->user()?->getSetting('theme_color', config('preferences.theme_color.default')) }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name', 'AsgardCRM'))</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{ Vite::fonts() }}
    </head>
    <body>
        <script>
            window.CSRF_TOKEN = @json(csrf_token());
            window.ICONS_BASE_URL = @json(url('/tabler-icons'));
        </script>

        @php $embed = request()->boolean('embed'); @endphp

        <div class="page">
            @unless ($embed)
                <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark" data-testid="sidebar">
                    <div class="container-fluid">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        <a href="{{ route('dashboard') }}" class="navbar-brand navbar-brand-autodark d-flex align-items-center gap-2">
                            <img src="{{ asset('logo.svg') }}" alt="{{ config('app.name', 'AsgardCRM') }}" class="navbar-brand-image" width="28" height="28">
                            @include('layouts._brand', ['dark' => true])
                        </a>

                        <div class="px-2 pt-3">
                            <div class="input-icon">
                                <span class="input-icon-addon">
                                    {!! icon('search') !!}
                                </span>
                                <input
                                    type="text"
                                    id="sidebar-menu-search"
                                    class="form-control form-control-dark"
                                    placeholder="{{ t('Cerca nel menù') }}"
                                    autocomplete="off"
                                    data-testid="sidebar-menu-search"
                                >
                            </div>
                        </div>

                        <div class="navbar-collapse collapse d-flex flex-column" id="sidebar-menu">
                            @yield('menu')
                        </div>
                    </div>
                </aside>

                <header class="navbar navbar-expand-md navbar-light sticky-top d-print-none" data-testid="topnavbar">
                    <div class="container-fluid">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                            <span class="navbar-toggler-icon"></span>
                        </button>

                        @unless (request()->routeIs('admin.*'))
                            <div class="dropdown flex-fill mx-3" style="max-width: 26rem;" data-testid="global-search">
                                <div class="input-icon">
                                    <span class="input-icon-addon">
                                        {!! icon('search') !!}
                                    </span>
                                    <input
                                        type="text"
                                        id="global-search-input"
                                        class="form-control"
                                        placeholder="{{ t('Cerca nelle entità...') }}"
                                        autocomplete="off"
                                        data-url="{{ route('search') }}"
                                        data-testid="global-search-input"
                                    >
                                </div>
                                <div
                                    class="dropdown-menu w-100"
                                    id="global-search-results"
                                    style="max-height: 24rem; overflow-y: auto;"
                                    data-no-results="{{ t('Nessun risultato') }}"
                                    data-testid="global-search-results"
                                ></div>
                            </div>
                        @endunless

                        <div class="navbar-nav flex-row order-md-last ms-auto align-items-center">
                            @foreach (\Fazzinipierluigi\CrmCore\Models\Entity::where('is_installed', true)->where('show_in_quick_access', true)->orderBy('quick_access_position')->get() as $quickAccessEntity)
                                @can("entity_{$quickAccessEntity->slug}.index")
                                    <button
                                        type="button"
                                        class="nav-link px-2 quick-access-link"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="bottom"
                                        title="{{ $quickAccessEntity->name }}"
                                        data-testid="quick-access-{{ $quickAccessEntity->slug }}"
                                        data-url="{{ $quickAccessEntity->indexUrl(['embed' => 1]) }}"
                                        data-name="{{ $quickAccessEntity->name }}"
                                    >
                                        {!! icon($quickAccessEntity->icon ?: 'star') !!}
                                    </button>
                                @endcan
                            @endforeach

                            <div class="nav-item dropdown me-2">
                                <a href="#" class="nav-link px-2" data-bs-toggle="dropdown" aria-label="Notifiche" data-testid="notifications-toggle">
                                    {!! icon('bell') !!}
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                    <div class="dropdown-item text-muted">{{ t('Nessuna notifica') }}</div>
                                </div>
                            </div>

                            <div class="nav-item dropdown">
                                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" data-testid="user-menu-toggle">
                                    <span class="avatar avatar-sm">{{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                                    <div class="d-none d-xl-block ps-2">
                                        <div data-testid="user-menu-name">{{ auth()->user()->name }}</div>
                                        <div class="mt-1 small text-muted" data-testid="user-menu-role">
                                            {{ auth()->user()->getRoles()->pluck('name')->join(', ') ?: t('Nessun ruolo') }}
                                        </div>
                                    </div>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                    <a href="{{ route('settings.edit') }}" class="dropdown-item">{{ t('Impostazioni') }}</a>
                                    <div class="dropdown-divider"></div>
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item">{{ t('Logout') }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
            @endunless

            <div class="page-wrapper">
                <div class="page-header d-print-none" aria-label="Page header">
                    <div class="container-fluid">
                        <div class="row g-2 align-items-center">
                            <div class="col">
                                <h2 class="page-title">
                                    <span class="text-truncate">@yield('title', config('app.name', 'AsgardCRM'))</span>
                                </h2>
                                @hasSection('breadcrumb')
                                    <div class="mt-1">
                                        <ol class="breadcrumb breadcrumb-arrows" aria-label="breadcrumbs">
                                            <li class="breadcrumb-item">
                                                <a href="{{ url('/') }}">
                                                    <!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                         stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                                        <path d="M5 12l-2 0l9 -9l9 9l-2 0" />
                                                        <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" />
                                                        <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                                                    </svg>
                                                </a>
                                            </li>
                                            @yield('breadcrumb')
                                            {{--
                                                breadcrumb section example:
                                                <li class="breadcrumb-item">
                                                    <a href="#">Library</a>
                                                </li>
                                                <li class="breadcrumb-item active" aria-current="page">
                                                    <a href="#">Articles</a>
                                                </li>
                                            --}}
                                        </ol>
                                    </div>
                                @endif
                            </div>
                            @hasSection('raccoon-layouts')
                                <div class="col-auto d-print-none">
                                    @yield('raccoon-layouts')
                                </div>
                            @endif
                            @hasSection('buttons')
                                <div class="col-auto ms-auto d-print-none">
                                    <div class="btn-list">
                                        @yield('buttons')
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="page-body">
                    <div class="container-fluid">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="quick-access-offcanvas" style="--tblr-offcanvas-width: calc(100vw - 15rem);" data-testid="quick-access-offcanvas">
            <div class="offcanvas-header border-bottom">
                <h2 class="offcanvas-title" id="quick-access-offcanvas-title" data-testid="quick-access-offcanvas-title"></h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ t('Chiudi') }}"></button>
            </div>
            <div class="offcanvas-body p-0">
                <iframe id="quick-access-offcanvas-frame" class="w-100 h-100 border-0" data-testid="quick-access-offcanvas-frame" title="{{ t('Accesso rapido') }}"></iframe>
            </div>
        </div>
    </body>
</html>
