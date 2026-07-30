<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\VersionHistory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every request behind the update wizard whenever the database's
 * recorded app_version Setting disagrees with the deployed code's
 * config('app.version'). Self-heals installs that predate version
 * tracking (no app_version Setting yet) by stamping the current code
 * version instead of forcing them through the wizard.
 */
class EnsureAppIsUpToDate
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        $dbVersion = Setting::valueFor(null, 'app_version');
        $codeVersion = (string) config('app.version');

        if ($dbVersion === null) {
            Setting::setValue(null, 'app_version', $codeVersion);

            VersionHistory::create([
                'version' => $codeVersion,
                'migrations_batch' => (int) DB::table('migrations')->max('batch'),
            ]);

            return $next($request);
        }

        if ($dbVersion === $codeVersion || $request->routeIs('update.*')) {
            return $next($request);
        }

        return redirect()->route('update.welcome');
    }
}
