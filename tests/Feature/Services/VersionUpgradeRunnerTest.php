<?php

use Fazzinipierluigi\AsgardCRM\Services\Upgrades\UpgradeStep;
use Fazzinipierluigi\AsgardCRM\Services\VersionUpgradeRunner;

class FakeUpgradeStep implements UpgradeStep
{
    public static array $log = [];

    public function __construct(private readonly string $stepVersion) {}

    public function version(): string
    {
        return $this->stepVersion;
    }

    public function upgrade(): void
    {
        self::$log[] = "up:{$this->stepVersion}";
    }

    public function downgrade(): void
    {
        self::$log[] = "down:{$this->stepVersion}";
    }
}

class FakeUpgradeStep110 extends FakeUpgradeStep
{
    public function __construct()
    {
        parent::__construct('1.1.0');
    }
}

class FakeUpgradeStep120 extends FakeUpgradeStep
{
    public function __construct()
    {
        parent::__construct('1.2.0');
    }
}

class FakeUpgradeStep200 extends FakeUpgradeStep
{
    public function __construct()
    {
        parent::__construct('2.0.0');
    }
}

beforeEach(function () {
    FakeUpgradeStep::$log = [];

    config(['crm.upgrades.steps' => [
        FakeUpgradeStep200::class,
        FakeUpgradeStep110::class,
        FakeUpgradeStep120::class,
    ]]);
});

test('pendingUpgrades returns steps strictly after $from and up to $to, ascending', function () {
    $versions = collect((new VersionUpgradeRunner)->pendingUpgrades('1.0.0', '1.2.0'))
        ->map(fn (UpgradeStep $step) => $step->version())
        ->all();

    expect($versions)->toBe(['1.1.0', '1.2.0']);
});

test('pendingUpgrades excludes a step at exactly $from', function () {
    $versions = collect((new VersionUpgradeRunner)->pendingUpgrades('1.1.0', '1.2.0'))
        ->map(fn (UpgradeStep $step) => $step->version())
        ->all();

    expect($versions)->toBe(['1.2.0']);
});

test('pendingDowngrades returns the same range in reverse order', function () {
    $versions = collect((new VersionUpgradeRunner)->pendingDowngrades('1.2.0', '1.0.0'))
        ->map(fn (UpgradeStep $step) => $step->version())
        ->all();

    expect($versions)->toBe(['1.2.0', '1.1.0']);
});

test('upgrade runs each pending step ->upgrade() in ascending order', function () {
    (new VersionUpgradeRunner)->upgrade('1.0.0', '1.2.0');

    expect(FakeUpgradeStep::$log)->toBe(['up:1.1.0', 'up:1.2.0']);
});

test('downgrade runs each pending step ->downgrade() in descending order', function () {
    (new VersionUpgradeRunner)->downgrade('1.2.0', '1.0.0');

    expect(FakeUpgradeStep::$log)->toBe(['down:1.2.0', 'down:1.1.0']);
});
