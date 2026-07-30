<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Gates every request behind the first-run installation wizard until
 * `storage/installed` exists. Self-heals for databases that already had
 * users before this marker existed (e.g. an app installed manually
 * before the wizard was introduced) instead of locking them out.
 */
class EnsureAppIsInstalled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('up')) {
            return $next($request);
        }

        if ($this->isInstalled()) {
            if ($request->routeIs('install.*')) {
                return redirect('/');
            }

            return $next($request);
        }

        if ($request->routeIs('install.*')) {
            return $next($request);
        }

        return redirect()->route('install.welcome');
    }

    private function isInstalled(): bool
    {
        $marker = storage_path('installed');

        if (file_exists($marker)) {
            return true;
        }

        try {
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                file_put_contents($marker, now()->toIso8601String());

                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
