<?php

namespace Fazzinipierluigi\CrmCore\Services\Upgrades;

/**
 * A version-specific task run by the update wizard alongside migrations
 * — for changes migrations can't express (data transforms, config/
 * settings changes, cache clearing, etc.). Register implementations, in
 * ascending version order, in config('crm.upgrades.steps').
 */
interface UpgradeStep
{
    /**
     * The version this step upgrades the app TO (must match a version
     * used in config('crm.upgrades.steps') ordering; compared with
     * version_compare()).
     */
    public function version(): string;

    public function upgrade(): void;

    /**
     * Reverse this step's effects — run when downgrading past this version.
     */
    public function downgrade(): void;
}
