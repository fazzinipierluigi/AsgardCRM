<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Update;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Services\VersionUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class UpdateController extends Controller
{
    public function welcome(VersionUpdateService $updateService): View
    {
        return view('crm::update.welcome', [
            'plan' => $updateService->plan(),
        ]);
    }

    public function run(VersionUpdateService $updateService): RedirectResponse
    {
        // composer install + npm ci + npm run build + migrate can easily
        // run past PHP's default max_execution_time.
        set_time_limit(0);

        try {
            $updateService->run();
        } catch (Throwable $e) {
            return redirect()->route('update.welcome')->withErrors(['update' => $e->getMessage()]);
        }

        return redirect()->route('dashboard')->with('status', 'Update complete.');
    }
}
