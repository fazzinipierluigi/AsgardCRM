<li class="list-group-item d-flex align-items-center gap-2" data-entity-id="{{ $entity->id }}" data-testid="quick-access-item-{{ $entity->slug }}">
    <span class="menu-drag-handle px-1" style="cursor: move" title="{{ t('Trascina per riordinare') }}">⠿</span>
    <span class="menu-item-icon">
        @if ($entity->icon)
            {!! icon($entity->icon) !!}
        @endif
    </span>
    <span class="flex-fill">{{ $entity->name }}</span>
    <button type="button" class="btn btn-icon btn-sm text-danger quick-access-remove-btn" title="{{ t('Rimuovi da Accesso rapido') }}" data-testid="quick-access-remove-{{ $entity->slug }}">✕</button>
</li>
