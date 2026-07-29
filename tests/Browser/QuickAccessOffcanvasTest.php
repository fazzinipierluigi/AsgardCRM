<?php

use App\Models\Entity;
use Laravel\Dusk\Browser;

test('a quick access icon opens the entity in a full-page offcanvas sheet, not a navigation', function () {
    Entity::create([
        'name' => 'Contatti',
        'slug' => 'contatti',
        'table_name' => 'entity_contatti',
        'is_installed' => true,
        'show_in_quick_access' => true,
    ]);
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/dashboard')
            ->waitFor('[data-testid="quick-access-contatti"]')
            ->click('[data-testid="quick-access-contatti"]')
            ->waitUntil('document.getElementById("quick-access-offcanvas").classList.contains("show")')
            ->assertVisible('[data-testid="quick-access-offcanvas"]')
            ->assertSeeIn('[data-testid="quick-access-offcanvas-title"]', 'Contatti');

        // The page underneath never navigated away.
        $browser->assertPathIs('/dashboard');

        // The sheet covers the content area up to the right edge of the
        // viewport, but starts to the right of the sidebar — not
        // Tabler's 400px offcanvas default (--tblr-offcanvas-width, not
        // Bootstrap's own --bs-offcanvas-width — see DOCUMENTATION.md
        // gotcha #17), and not the full 100vw either (that used to
        // cover the sidebar menu too).
        $rects = $browser->script([
            'return [document.getElementById("quick-access-offcanvas").getBoundingClientRect(), document.querySelector("[data-testid=\"sidebar\"]").getBoundingClientRect(), window.innerWidth];',
        ]);
        [$offcanvasRect, $sidebarRect, $innerWidth] = $rects[0];
        expect($offcanvasRect['left'])->toBeGreaterThanOrEqual($sidebarRect['right'] - 1);
        expect($offcanvasRect['right'])->toBeGreaterThan($innerWidth - 5);

        // The iframe loads the entity's own page, embedded without its
        // own sidebar/topbar (see layouts/app.blade.php's `embed` flag).
        $browser->withinFrame('#quick-access-offcanvas-frame', function (Browser $frame) {
            $frame->waitFor('[data-testid="entity-records-grid"]', 5)
                ->assertMissing('[data-testid="topnavbar"]')
                ->assertMissing('[data-testid="sidebar"]');
        });

        // Closing the sheet drops the iframe content and leaves the
        // dashboard exactly as it was — no reload, still on /dashboard.
        $browser->click('#quick-access-offcanvas .btn-close')
            ->waitUntil('!document.getElementById("quick-access-offcanvas").classList.contains("show")')
            ->assertPathIs('/dashboard')
            ->assertVisible('[data-testid="menu-dashboard"]');
    });
});
