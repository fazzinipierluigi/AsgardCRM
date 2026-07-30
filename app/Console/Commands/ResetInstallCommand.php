<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

/**
 * Dev/testing helper: wipes the database back to empty and removes the
 * `storage/installed` marker, so the next request is treated as a fresh,
 * never-installed app — lets the install wizard be re-run and re-tested
 * without a full re-clone. Prompts for confirmation in production
 * (ConfirmableTrait, same safety net as `migrate:fresh`); does not touch
 * .env, so the database connection configured there keeps working.
 */
class ResetInstallCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'app:reset-install {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe the database and remove the installed marker, to re-test the install wizard';

    public function handle(): int
    {
        if (! $this->confirmToProceed('This will drop every table in the database.')) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        $marker = storage_path('installed');

        if (file_exists($marker)) {
            unlink($marker);
        }

        $this->info('Database wiped and installed marker removed — the app will now go through the install wizard again.');

        return self::SUCCESS;
    }
}
