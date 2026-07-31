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

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3" style="position: sticky; top: 1rem;" data-testid="documents-filters">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Filtri') }}</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('documents.index', $folder ? ['folder' => $folder->id] : []) }}">
                        <div class="input-icon mb-2">
                            <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="{{ t('Cerca documento...') }}" data-testid="documents-search-input">
                            <span class="input-icon-addon">
                                {!! icon('search') !!}
                            </span>
                        </div>
                    </form>
                </div>
                <div class="list-group list-group-flush" data-testid="documents-folder-tree">
                    <a href="{{ route('documents.index') }}" class="list-group-item list-group-item-action d-flex align-items-center {{ $folder === null && $search === '' ? 'active' : '' }}">
                        <span class="me-2" style="width: 1.25rem;">{!! icon('folder') !!}</span>
                        {{ t('Tutte le cartelle') }}
                    </a>
                    @foreach ($folderTree as $node)
                        @include('documents._folder_tree', ['node' => $node, 'currentFolder' => $folder, 'depth' => 0])
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" data-testid="documents-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>{{ t('Nome') }}</th>
                                <th>{{ t('Dimensione') }}</th>
                                <th>{{ t('Caricato da') }}</th>
                                <th>{{ t('Data') }}</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($subfolders as $subfolder)
                                <tr data-testid="document-folder-row-{{ $subfolder->id }}">
                                    <td>{!! icon('folder') !!}</td>
                                    <td>
                                        <a href="{{ route('documents.index', ['folder' => $subfolder->id]) }}">{{ $subfolder->name }}</a>
                                    </td>
                                    <td colspan="3" class="text-secondary">{{ t('Cartella') }}</td>
                                    <td class="text-end">
                                        @if ($canDelete)
                                            <form method="POST" action="{{ route('documents.folders.destroy', $subfolder) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" data-testid="document-folder-delete-btn-{{ $subfolder->id }}">{{ t('Elimina') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                            @endforelse

                            @forelse ($documents as $document)
                                <tr data-testid="document-row-{{ $document->id }}">
                                    <td>{!! icon(\App\Support\DocumentIconResolver::forFilename($document->original_filename)) !!}</td>
                                    <td>
                                        <a href="{{ route('documents.download', $document) }}">{{ $document->nome }}</a>
                                        @if ($document->descrizione)
                                            <div class="text-secondary small">{{ $document->descrizione }}</div>
                                        @endif
                                    </td>
                                    <td>{{ number_format($document->file_size / 1024, 0) }} KB</td>
                                    <td>{{ $document->owner?->name }}</td>
                                    <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="text-end">
                                        <div class="btn-list flex-nowrap">
                                            <a href="{{ route('documents.download', $document) }}" class="btn btn-sm btn-outline-secondary">{{ t('Scarica') }}</a>
                                            @if ($canCreate)
                                                <a href="{{ route('documents.edit', $document) }}" class="btn btn-sm btn-outline-primary">{{ t('Modifica') }}</a>
                                            @endif
                                            @if ($canDelete)
                                                <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm({{ Js::from(t('Confermi l\'eliminazione?')) }});">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-testid="document-delete-btn-{{ $document->id }}">{{ t('Elimina') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                @if ($subfolders->isEmpty())
                                    <tr>
                                        <td colspan="6" class="text-secondary" data-testid="documents-empty">{{ t('Nessun documento in questa cartella.') }}</td>
                                    </tr>
                                @endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
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

    @vite('resources/js/documents.js')
@endsection
