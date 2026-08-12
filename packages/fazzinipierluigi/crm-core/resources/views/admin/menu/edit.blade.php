@extends('layouts.admin')

@section('title', t('Gestione menù'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.menu.edit') }}">{{ t('Gestione menù') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="menu-builder-form" class="btn btn-primary" data-testid="menu-builder-submit">{{ t('Salva menù') }}</button>
@endsection

@section('content')
    @if (session('status') === 'menu-updated')
        <div class="alert alert-success" data-testid="menu-updated">{{ t('Menù aggiornato.') }}</div>
    @endif

    <div class="text-muted mb-3">{{ t('Trascina le voci per riordinarle, usa le frecce per spostarle tra i due elenchi e la stella per aggiungerle all\'accesso rapido.') }}</div>

    <form action="{{ route('admin.menu.update') }}" method="POST" id="menu-builder-form">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ t('Menu principale') }}</h3>
                    </div>
                    <ul class="list-group list-group-flush" id="menu-visible-list" data-testid="menu-visible-list">
                        @foreach ($visibleEntities as $entity)
                            @include('crm::admin.menu._item', ['entity' => $entity])
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ t('Altre entità') }}</h3>
                    </div>
                    <ul class="list-group list-group-flush" id="menu-hidden-list" data-testid="menu-hidden-list">
                        @foreach ($hiddenEntities as $entity)
                            @include('crm::admin.menu._item', ['entity' => $entity])
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ t('Accesso rapido') }}</h3>
                    </div>
                    <ul class="list-group list-group-flush" id="quick-access-list" data-testid="quick-access-list">
                        @foreach ($quickAccessEntities as $entity)
                            @include('crm::admin.menu._quick_access_item', ['entity' => $entity])
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div id="menu-builder-hidden-inputs"></div>
    </form>

    @vite('resources/js/menu-builder.js')
@endsection
