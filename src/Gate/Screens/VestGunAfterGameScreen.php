<?php

namespace App\Gate\Screens;

use App\Gate\Settings\GateSettings;
use App\Gate\Settings\VestGunAfterGameSettings;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<VestGunAfterGameSettings>
 */
class VestGunAfterGameScreen extends GateScreen implements ReloadTimerInterface, WithSettings
{
    use WithReloadTimer;

    private VestGunAfterGameSettings $settings;

    /**
     * @inheritDoc
     */
    public static function getName(): string {
        return lang('Připněte zbraň na vestu', domain: 'gate', context: 'screens');
    }

    public static function getDescription(): string {
        return lang('Informační obrazovka s animací připnutí zbraně na vestu.', domain: 'gate', context: 'screens');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey(): string {
        return 'gate.screens.vest_gun_after_game';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm(): string {
        return 'gate/settings/vestGunAfterGame.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data): GateSettings {
        return new VestGunAfterGameSettings(
            isset($data['time']) ? (int) $data['time'] : null,
        );
    }

    /**
     * @inheritDoc
     */
    public function run(): ResponseInterface {
        return $this->view(
            'gate/screens/vestGunAfterGame',
            [
            'settings' => $this->getSettings(),
            'addCss'   => ['gate/vestGunAfterGame.css'],
            //'addJs'   => ['gate/vestGunAfterGame.js'],
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettings(): VestGunAfterGameSettings {
        if (!isset($this->settings)) {
            $this->settings = new VestGunAfterGameSettings();
        }
        return $this->settings;
    }

    /**
     * @inheritDoc
     */
    public function setSettings(GateSettings $settings): static {
        $this->settings = $settings;
        return $this;
    }
}
