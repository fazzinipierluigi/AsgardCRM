<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Shells out to composer/npm to bring vendor/node_modules and the built
 * frontend assets in line with whatever composer.lock/package-lock.json
 * are currently on disk — used by the update wizard before running
 * migrations, since a `git pull`/checkout alone doesn't refresh either.
 *
 * COMPOSER_BINARY/NPM_BINARY let the binary path be overridden when it
 * isn't on the web server process's PATH (e.g. npm installed via nvm,
 * which only lives on the interactive shell's PATH).
 *
 * Runs `npm run build` against the CONSUMING HOST's own package.json —
 * this package's own pre-built assets (public/vendor/crm) are unrelated
 * and never rebuilt by this. A host with no frontend build of its own
 * (npm/package.json) shouldn't wire this service up at all.
 */
class DependencyInstaller
{
    public function install(): void
    {
        $this->run([env('COMPOSER_BINARY', 'composer'), 'install', '--no-dev', '--optimize-autoloader']);
        $this->run([env('NPM_BINARY', 'npm'), 'ci']);
        $this->run([env('NPM_BINARY', 'npm'), 'run', 'build']);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): void
    {
        $process = new Process($command, base_path());
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                "Command failed: %s\n%s",
                implode(' ', $command),
                $process->getErrorOutput() ?: $process->getOutput()
            ));
        }
    }
}
