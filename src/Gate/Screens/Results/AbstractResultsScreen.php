<?php

namespace App\Gate\Screens\Results;

use App\Gate\Screens\GateScreen;
use App\Gate\Screens\ReloadTimerInterface;
use App\Gate\Screens\WithReloadTimer;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\ResultsSettings;

/**
 *
 */
abstract class AbstractResultsScreen extends GateScreen implements ResultsScreenInterface, ReloadTimerInterface
{
    use WithResultsSettings;
    use WithReloadTimer;

    public static function getGroup() : string {
        return lang('Výsledky', context: 'gate-screens-groups');
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/results.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        return new ResultsSettings(isset($data['time']) ? (int) $data['time'] : null);
    }

    public function isActive() : bool {
        bdump($this->getReloadTimer());
        return $this->getReloadTimer() > 0;
    }

    /**
     * Get the number of seconds before this screen should be reloaded (inactive).
     *
     * @return int Seconds before reload
     */
    public function getReloadTimer() : int {
        return $this->getSettings()->time - (time() - ($this->getGame()?->end?->getTimestamp() ?? 0)) + 2;
    }
}