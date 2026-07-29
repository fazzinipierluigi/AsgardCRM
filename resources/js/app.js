import '@tabler/core/dist/js/tabler.min.js';
import { RaccoonGrid } from 'raccoon-tables';
// Import the specific submodules, not the aggregate 'bootstrap' entry
// point: that index re-exports (and re-runs the data-api side effects
// of) every component, including Dropdown — which double-registers its
// document click listener on top of the one already wired by Tabler's
// own bundled Bootstrap JS, breaking the topbar user-menu dropdown
// (it opens then immediately closes). Importing single files sidesteps
// that entirely.
import Offcanvas from 'bootstrap/js/dist/offcanvas';
import Tooltip from 'bootstrap/js/dist/tooltip';
import { icon } from './icon.js';
import './tom-select.js';
import './entity-button-field.js';
import './table-field.js';
import './entity-list-widgets.js';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.RaccoonGrid = RaccoonGrid;
window.icon = icon;
window.Swal = Swal.mixin({ buttonsStyling: false, customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-link' } });

// Quick-access topbar icons (see layouts/app.blade.php) rely on Bootstrap
// tooltips to show an entity's name — Tabler's bundled JS doesn't
// auto-init them.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new Tooltip(el);
    });
});

// Quick-access topbar icons open the target entity in a full-page
// offcanvas sheet (iframe) instead of navigating away, so the page
// underneath is left exactly as it was once the sheet is closed.
document.addEventListener('DOMContentLoaded', function () {
    var offcanvasEl = document.getElementById('quick-access-offcanvas');

    if (!offcanvasEl) {
        return;
    }

    var offcanvas = new Offcanvas(offcanvasEl);
    var frame = document.getElementById('quick-access-offcanvas-frame');
    var title = document.getElementById('quick-access-offcanvas-title');

    document.querySelectorAll('.quick-access-link').forEach(function (link) {
        link.addEventListener('click', function () {
            title.textContent = link.dataset.name;
            frame.src = link.dataset.url;
            offcanvas.show();
        });
    });

    // Drop the iframe's content once the sheet is closed, so reopening
    // it (or opening a different entity) always starts from a clean
    // load rather than showing stale content for a moment.
    offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
        frame.src = 'about:blank';
        title.textContent = '';
    });
});

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
