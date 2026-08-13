<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use Fazzinipierluigi\AsgardCRM\Services\Upgrades\UpgradeStep;

/**
 * Resolves and runs the Fazzinipierluigi\AsgardCRM\Services\Upgrades\UpgradeStep
 * classes registered in config('crm.upgrades.steps') that fall between two
 * app versions, in the direction the update wizard needs.
 */
class VersionUpgradeRunner
{
    /**
     * @return array<int, UpgradeStep>
     */
    public function pendingUpgrades(string $from, string $to): array
    {
        return $this->stepsBetween($from, $to);
    }

    /**
     * Same range as pendingUpgrades(), reversed — undo the most recently
     * applied step first.
     *
     * @return array<int, UpgradeStep>
     */
    public function pendingDowngrades(string $from, string $to): array
    {
        return array_reverse($this->stepsBetween($to, $from));
    }

    public function upgrade(string $from, string $to): void
    {
        foreach ($this->pendingUpgrades($from, $to) as $step) {
            $step->upgrade();
        }
    }

    public function downgrade(string $from, string $to): void
    {
        foreach ($this->pendingDowngrades($from, $to) as $step) {
            $step->downgrade();
        }
    }

    /**
     * Steps with version() strictly greater than $from and at most $to,
     * ascending.
     *
     * @return array<int, UpgradeStep>
     */
    private function stepsBetween(string $from, string $to): array
    {
        $steps = array_map(fn (string $class) => app($class), config('crm.upgrades.steps', []));

        $steps = array_values(array_filter(
            $steps,
            fn (UpgradeStep $step) => version_compare($step->version(), $from, '>')
                && version_compare($step->version(), $to, '<=')
        ));

        usort($steps, fn (UpgradeStep $a, UpgradeStep $b) => version_compare($a->version(), $b->version()));

        return $steps;
    }
}
