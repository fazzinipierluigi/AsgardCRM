import '@tabler/core/dist/js/tabler.min.js';
import { RaccoonGrid } from 'raccoon-tables';
import { icon } from './icon.js';
import './tom-select.js';

window.RaccoonGrid = RaccoonGrid;
window.icon = icon;

/**
 * Wire a RaccoonGrid instance up to the laraccoon-layouts dropdown
 * (@raccoonLayoutsDropdown / @raccoonLayoutsScripts): the package's
 * own script calls the bare global functions getLayout()/setLayout(),
 * not grid methods, so every page with a grid + the dropdown must call
 * this once, right after grid.render(...), to bind them to that page's
 * grid instance. resetLayout() restores the grid's initial layout,
 * used when the dropdown's "— Standard —" placeholder is selected.
 */
window.wireRaccoonLayouts = function (grid) {
    var initialLayout = grid.getLayout();

    window.getLayout = function () {
        return grid.getLayout();
    };
    window.setLayout = function (layout) {
        grid.setLayout(layout);
    };
    window.resetLayout = function () {
        grid.setLayout(initialLayout);
    };
};

/**
 * Sidebar menu search: filters every `.nav-link-title` entry under
 * #sidebar-menu as the user types, across every `.navbar-nav` block
 * (main menu + the bottom-pinned one). A section title (`.subheader`,
 * see layouts/_menu_section_title.blade.php) is hidden along with its
 * group whenever none of the items below it (until the next section
 * title) match.
 */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('sidebar-menu-search');
    if (!input) {
        return;
    }

    input.addEventListener('input', function () {
        var term = input.value.trim().toLowerCase();

        document.querySelectorAll('#sidebar-menu .navbar-nav').forEach(function (nav) {
            var pendingTitle = null;
            var groupHasMatch = false;

            Array.from(nav.children).forEach(function (li) {
                var title = li.querySelector(':scope > .subheader');
                if (title) {
                    if (pendingTitle) {
                        pendingTitle.classList.toggle('d-none', !groupHasMatch);
                    }
                    pendingTitle = li;
                    groupHasMatch = false;
                    return;
                }

                var label = li.querySelector('.nav-link-title');
                var matches = !label || !term || label.textContent.toLowerCase().includes(term);
                li.classList.toggle('d-none', !matches);
                if (matches) {
                    groupHasMatch = true;
                }
            });

            if (pendingTitle) {
                pendingTitle.classList.toggle('d-none', !groupHasMatch);
            }
        });
    });
});

/**
 * Top navbar global search (main app section only, see layouts/app.blade.php):
 * debounced fetch against GlobalSearchController::search(), rendered as a
 * Bootstrap dropdown grouped by entity.
 */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('global-search-input');
    var menu = document.getElementById('global-search-results');
    if (!input || !menu) {
        return;
    }

    var minLength = 2;
    var debounceMs = 250;
    var timer = null;
    var requestId = 0;

    function closeMenu() {
        menu.classList.remove('show');
    }

    function openMenu() {
        menu.classList.add('show');
    }

    function clearMenu() {
        menu.textContent = '';
    }

    function renderGroups(groups) {
        clearMenu();

        if (!groups.length) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item disabled';
            empty.textContent = menu.dataset.noResults || 'No results';
            menu.appendChild(empty);
            openMenu();
            return;
        }

        groups.forEach(function (group) {
            var header = document.createElement('h6');
            header.className = 'dropdown-header';
            header.textContent = group.entity.name;
            menu.appendChild(header);

            group.records.forEach(function (record) {
                var link = document.createElement('a');
                link.className = 'dropdown-item';
                link.href = record.url;
                link.textContent = record.title;
                menu.appendChild(link);
            });
        });

        openMenu();
    }

    input.addEventListener('input', function () {
        var term = input.value.trim();
        clearTimeout(timer);

        if (term.length < minLength) {
            closeMenu();
            return;
        }

        timer = setTimeout(function () {
            var thisRequestId = ++requestId;

            fetch(input.dataset.url + '?q=' + encodeURIComponent(term), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (thisRequestId === requestId) {
                        renderGroups(data.results || []);
                    }
                })
                .catch(function () {
                    if (thisRequestId === requestId) {
                        closeMenu();
                    }
                });
        }, debounceMs);
    });

    document.addEventListener('click', function (e) {
        if (!input.closest('.dropdown').contains(e.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
        }
    });
});
