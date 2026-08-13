<li class="nav-item d-flex align-items-center gap-1 repeatable-row px-1" role="presentation" data-tab-token="{{ $tabToken }}">
    <input type="hidden" name="tabs[{{ $tabToken }}][name]" class="tab-name-input" value="{{ $tab?->name }}">
    <span class="tab-drag-handle px-1" style="cursor: move" title="{{ t('Trascina per riordinare') }}" data-testid="tab-drag-handle">⠿</span>
    <button
        class="nav-link tab-switch-btn {{ $active ? 'active' : '' }}"
        type="button"
        data-bs-toggle="tab"
        data-bs-target="#tab-pane-{{ $tabToken }}"
        role="tab"
        data-testid="tab-switch-btn"
    >
        <span class="tab-name-label">{{ $tab?->name ?: t('Nuovo tab') }}</span>
    </button>
    <button type="button" class="btn btn-icon tab-rename-btn ms-1" title="{{ t('Rinomina') }}" data-testid="tab-rename-btn">✎</button>
</li>
