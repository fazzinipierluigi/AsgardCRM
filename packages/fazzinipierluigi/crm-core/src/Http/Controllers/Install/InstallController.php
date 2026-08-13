<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Install;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Install\StoreAdminRequest;
use Fazzinipierluigi\CrmCore\Http\Requests\Install\StoreDatabaseRequest;
use Fazzinipierluigi\CrmCore\Services\ApplicationInstaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class InstallController extends Controller
{
    public function welcome(): View
    {
        return view('crm::install.welcome', [
            'checks' => $this->requirementChecks(),
        ]);
    }

    public function database(): View
    {
        return view('crm::install.database', [
            'old' => session('install.database', ['driver' => 'pgsql']),
        ]);
    }

    public function storeDatabase(StoreDatabaseRequest $request, ApplicationInstaller $installer): RedirectResponse
    {
        $data = $this->normalizeDatabaseData($request->validated());

        try {
            $installer->testConnection($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['database' => $e->getMessage()]);
        }

        session(['install.database' => $data]);

        return redirect()->route('install.admin');
    }

    public function testConnection(StoreDatabaseRequest $request, ApplicationInstaller $installer): JsonResponse
    {
        $data = $this->normalizeDatabaseData($request->validated());

        try {
            $installer->testConnection($data);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    public function admin(): View|RedirectResponse
    {
        if (! session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        return view('crm::install.admin', [
            'old' => session('install.admin', []),
        ]);
    }

    public function storeAdmin(StoreAdminRequest $request): RedirectResponse
    {
        if (! session()->has('install.database')) {
            return redirect()->route('install.database');
        }

        session(['install.admin' => $request->safe()->only(['name', 'username', 'email', 'password'])]);

        return redirect()->route('install.finish');
    }

    public function finish(): View|RedirectResponse
    {
        if (! session()->has('install.database') || ! session()->has('install.admin')) {
            return redirect()->route('install.database');
        }

        return view('crm::install.finish', [
            'database' => session('install.database'),
            'admin' => session('install.admin'),
        ]);
    }

    public function run(ApplicationInstaller $installer): RedirectResponse
    {
        if (! session()->has('install.database') || ! session()->has('install.admin')) {
            return redirect()->route('install.database');
        }

        try {
            $user = $installer->install(session('install.database'), session('install.admin'));
        } catch (Throwable $e) {
            return redirect()->route('install.finish')->withErrors(['install' => $e->getMessage()]);
        }

        session()->forget(['install.database', 'install.admin']);

        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Installation complete.');
    }

    /**
     * @return array<int, array{label: string, ok: bool}>
     */
    private function requirementChecks(): array
    {
        return [
            ['label' => 'PHP 8.3 or higher', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['label' => '.env file is writable', 'ok' => is_writable(base_path('.env'))],
            ['label' => 'storage/ directory is writable', 'ok' => is_writable(storage_path())],
            ['label' => 'bootstrap/cache/ directory is writable', 'ok' => is_writable(base_path('bootstrap/cache'))],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeDatabaseData(array $data): array
    {
        if ($data['driver'] === 'sqlite') {
            $data['database'] = $data['database'] ?: database_path('database.sqlite');
            unset($data['host'], $data['port'], $data['username'], $data['password']);

            return $data;
        }

        if (empty($data['port'])) {
            $data['port'] = match ($data['driver']) {
                'mysql', 'mariadb' => 3306,
                'pgsql' => 5432,
                default => null,
            };
        }

        return $data;
    }
}
