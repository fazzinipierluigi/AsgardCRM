import Sortable from 'sortablejs';

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('menu-builder-form');

    if (!form) {
        return;
    }

    var visibleList = document.getElementById('menu-visible-list');
    var hiddenList = document.getElementById('menu-hidden-list');
    var quickAccessList = document.getElementById('quick-access-list');
    var hiddenInputsContainer = document.getElementById('menu-builder-hidden-inputs');

    // Drag-and-drop reordering (same list only — moving between the
    // "Menu principale"/"Altre entità" lists happens via the swap
    // button below instead, same convention as entity-builder.js).
    function makeSortable(container) {
        if (!container) {
            return;
        }

        Sortable.create(container, { handle: '.menu-drag-handle', animation: 150 });
    }

    makeSortable(visibleList);
    makeSortable(quickAccessList);

    function buildQuickAccessItem(menuItem) {
        var li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center gap-2';
        li.dataset.entityId = menuItem.dataset.entityId;
        li.setAttribute('data-testid', 'quick-access-item-' + menuItem.dataset.entitySlug);

        var handle = document.createElement('span');
        handle.className = 'menu-drag-handle px-1';
        handle.style.cursor = 'move';
        handle.textContent = '⠿';
        li.appendChild(handle);

        var iconSpan = document.createElement('span');
        iconSpan.className = 'menu-item-icon';
        iconSpan.innerHTML = menuItem.querySelector('.menu-item-icon').innerHTML;
        li.appendChild(iconSpan);

        var nameSpan = document.createElement('span');
        nameSpan.className = 'flex-fill';
        nameSpan.textContent = menuItem.dataset.entityName;
        li.appendChild(nameSpan);

        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-icon btn-sm text-danger quick-access-remove-btn';
        removeBtn.setAttribute('data-testid', 'quick-access-remove-' + menuItem.dataset.entitySlug);
        removeBtn.textContent = '✕';
        li.appendChild(removeBtn);

        return li;
    }

    form.addEventListener('click', function (event) {
        var visibilityBtn = event.target.closest('.menu-toggle-visibility-btn');
        if (visibilityBtn) {
            var item = visibilityBtn.closest('.menu-item');
            var targetList = item.parentElement === visibleList ? hiddenList : visibleList;
            targetList.appendChild(item);
            return;
        }

        var quickAccessBtn = event.target.closest('.menu-toggle-quick-access-btn');
        if (quickAccessBtn) {
            var menuItem = quickAccessBtn.closest('.menu-item');
            var existing = quickAccessList.querySelector('[data-entity-id="' + menuItem.dataset.entityId + '"]');

            if (existing) {
                existing.remove();
                quickAccessBtn.classList.remove('text-warning');
                return;
            }

            quickAccessList.appendChild(buildQuickAccessItem(menuItem));
            quickAccessBtn.classList.add('text-warning');
            return;
        }

        var removeBtn = event.target.closest('.quick-access-remove-btn');
        if (removeBtn) {
            var quickAccessItem = removeBtn.closest('li');
            var sourceBtn = form.querySelector('.menu-item[data-entity-id="' + quickAccessItem.dataset.entityId + '"] .menu-toggle-quick-access-btn');

            quickAccessItem.remove();
            sourceBtn?.classList.remove('text-warning');
        }
    });

    function appendHiddenInput(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        hiddenInputsContainer.appendChild(input);
    }

    // Order is expressed by DOM position (drag reordering just moves
    // elements), so the submitted arrays are built fresh right before
    // the form actually posts.
    form.addEventListener('submit', function () {
        hiddenInputsContainer.innerHTML = '';

        visibleList.querySelectorAll('.menu-item').forEach(function (item) {
            appendHiddenInput('visible[]', item.dataset.entityId);
        });

        quickAccessList.querySelectorAll('li').forEach(function (item) {
            appendHiddenInput('quick_access[]', item.dataset.entityId);
        });
    });
});
