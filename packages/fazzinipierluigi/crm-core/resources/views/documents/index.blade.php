@extends('layouts.base')

@section('title', t('Documenti'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('documents.index') }}">{{ t('Documenti') }}</a>
    </li>
    @foreach ($breadcrumb as $crumb)
        <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" aria-current="{{ $loop->last ? 'page' : '' }}">
            <a href="{{ route('documents.index', ['folder' => $crumb->id]) }}">{{ $crumb->name }}</a>
        </li>
    @endforeach
@endsection

@section('buttons')
    @if ($canCreate)
        <button type="button" class="btn btn-outline-primary" data-testid="document-new-folder-btn">
            {{ t('Nuova cartella') }}
        </button>
        <a href="{{ route('documents.create', $folder ? ['folder' => $folder->id] : []) }}" class="btn btn-primary" data-testid="document-upload-link">
            {{ t('Carica documento') }}
        </a>
    @endif
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success" data-testid="documents-status">
            @switch(session('status'))
                @case('folder-created')
                    {{ t('Cartella creata correttamente.') }}
                    @break
                @case('folder-deleted')
                    {{ t('Cartella eliminata correttamente.') }}
                    @break
                @case('document-uploaded')
                    {{ t('Documento caricato correttamente.') }}
                    @break
                @case('document-updated')
                    {{ t('Documento aggiornato correttamente.') }}
                    @break
                @case('document-deleted')
                    {{ t('Documento eliminato correttamente.') }}
                    @break
            @endswitch
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" data-testid="documents-error">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" data-testid="documents-validation-errors">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        // Every folder along the path down to the currently open one (itself
        // included) — expanded by default so the tree always reveals where
        // you currently are, instead of collapsing back to the root on every navigation.
        $expandedIds = collect($breadcrumb)->pluck('id')->all();
    @endphp

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3" style="position: sticky; top: 1rem;" data-testid="documents-sidebar">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Cartelle') }}</h3>
                </div>
                <div class="card-body pb-2">
                    <form method="GET" action="{{ route('documents.index', $folder ? ['folder' => $folder->id] : []) }}">
                        <div class="input-icon mb-2">
                            <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="{{ t('Cerca documento...') }}" data-testid="documents-search-input">
                            <span class="input-icon-addon">
                                {!! icon('search') !!}
                            </span>
                        </div>
                    </form>
                </div>
                <div class="document-tree card-body pt-0" data-testid="documents-folder-tree">
                    <div class="document-tree-node">
                        <div class="d-flex align-items-center document-tree-row {{ $folder === null && $search === '' ? 'active' : '' }}">
                            <span class="document-tree-spacer"></span>
                            <a href="{{ route('documents.index') }}" class="document-tree-link flex-fill d-flex align-items-center text-reset text-decoration-none">
                                <span class="me-2" style="width: 1.25rem;">{!! icon('folder') !!}</span>
                                {{ t('Tutte le cartelle') }}
                            </a>
                        </div>
                    </div>
                    @foreach ($folderTree as $node)
                        @include('crm::documents._folder_tree', ['node' => $node, 'currentFolder' => $folder, 'expandedIds' => $expandedIds])
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-6 g-3" data-testid="documents-grid">
                @forelse ($subfolders as $subfolder)
                    <div class="col">
                        <div class="card card-sm h-100" data-testid="document-folder-row-{{ $subfolder->id }}">
                            <a href="{{ route('documents.index', ['folder' => $subfolder->id]) }}" class="card-body d-block text-center py-4 text-reset text-decoration-none" title="{{ $subfolder->name }}">
                                <span class="d-inline-block icon-lg text-yellow mb-2">{!! icon('folder') !!}</span>
                                <div class="text-truncate small fw-medium">{{ $subfolder->name }}</div>
                            </a>
                            @if ($canDelete)
                                <div class="card-footer p-1 text-center">
                                    <form method="POST" action="{{ route('documents.folders.destroy', $subfolder) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-ghost-danger w-100" data-testid="document-folder-delete-btn-{{ $subfolder->id }}">{{ t('Elimina') }}</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                @endforelse

                @forelse ($documents as $document)
                    <div class="col">
                        <div class="card card-sm h-100" data-testid="document-row-{{ $document->id }}">
                            <a href="{{ route('documents.download', $document) }}" class="card-body d-block text-center py-4 text-reset text-decoration-none" title="{{ $document->nome }}{{ $document->descrizione ? ' — '.$document->descrizione : '' }}">
                                <span class="d-inline-block icon-lg mb-2">{!! icon(\Fazzinipierluigi\CrmCore\Support\DocumentIconResolver::forFilename($document->original_filename)) !!}</span>
                                <div class="text-truncate small fw-medium">{{ $document->nome }}</div>
                                <div class="text-secondary small">{{ number_format($document->file_size / 1024, 0) }} KB</div>
                            </a>
                            @if ($canCreate || $canDelete)
                                <div class="card-footer p-1 d-flex justify-content-center gap-1">
                                    @if ($canCreate)
                                        <a href="{{ route('documents.edit', $document) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="{{ t('Modifica') }}">
                                            {!! icon('pencil') !!}
                                        </a>
                                    @endif
                                    @if ($canDelete)
                                        <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" data-testid="document-delete-btn-{{ $document->id }}" title="{{ t('Elimina') }}">
                                                {!! icon('trash') !!}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    @if ($subfolders->isEmpty())
                        <div class="col-12">
                            <div class="text-secondary" data-testid="documents-empty">{{ t('Nessun documento in questa cartella.') }}</div>
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>

    @if ($canCreate)
        <div class="modal" id="new-folder-modal" tabindex="-1" data-testid="new-folder-modal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('documents.folders.store') }}">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $folder?->id }}">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ t('Nuova cartella') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">{{ t('Nome cartella') }}</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required autofocus data-testid="new-folder-name-input">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary" data-testid="new-folder-submit">{{ t('Crea') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <style>
        .document-tree-row { border-radius: var(--tblr-border-radius); padding: .125rem .25rem; }
        .document-tree-row:hover { background: var(--tblr-bg-surface-secondary); }
        .document-tree-row.active { background: var(--tblr-primary-lt); }
        .document-tree-link { padding: .25rem 0; color: inherit; }
        .document-tree-toggle,
        .document-tree-spacer { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; flex-shrink: 0; }
        .document-tree-toggle { background: none; border: 0; padding: 0; color: var(--tblr-secondary); cursor: pointer; }
        .document-tree-toggle .icon { transition: transform .15s ease; transform: rotate(90deg); }
        .document-tree-toggle.collapsed .icon { transform: rotate(0deg); }
    </style>

    @vite('resources/js/documents.js', 'vendor/crm')
@endsection
