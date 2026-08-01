@php
    $folder = $node['folder'];
    $children = $node['children'];
    $hasChildren = $children->isNotEmpty();
    $isActive = $currentFolder?->id === $folder->id;
    $isExpanded = in_array($folder->id, $expandedIds, true);
    $collapseId = 'document-tree-folder-'.$folder->id;
@endphp

<div class="document-tree-node">
    <div class="d-flex align-items-center document-tree-row {{ $isActive ? 'active' : '' }}">
        @if ($hasChildren)
            <button
                type="button"
                class="document-tree-toggle {{ $isExpanded ? '' : 'collapsed' }}"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}"
                aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
                aria-controls="{{ $collapseId }}"
            >
                {!! icon('chevron-right') !!}
            </button>
        @else
            <span class="document-tree-spacer"></span>
        @endif

        <a
            href="{{ route('documents.index', ['folder' => $folder->id]) }}"
            class="document-tree-link flex-fill d-flex align-items-center text-reset text-decoration-none"
            data-testid="document-folder-tree-item-{{ $folder->id }}"
        >
            <span class="me-2" style="width: 1.25rem;">{!! icon($isActive ? 'folder-open' : 'folder') !!}</span>
            {{ $folder->name }}
        </a>
    </div>

    @if ($hasChildren)
        <div class="collapse ps-4 {{ $isExpanded ? 'show' : '' }}" id="{{ $collapseId }}">
            @foreach ($children as $child)
                @include('documents._folder_tree', ['node' => $child, 'currentFolder' => $currentFolder, 'expandedIds' => $expandedIds])
            @endforeach
        </div>
    @endif
</div>
