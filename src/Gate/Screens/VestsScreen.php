<?php

namespace App\Gate\Screens;

use App\GameModels\Vest;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\VestsSettings;
use InvalidArgumentException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<VestsSettings>
 */
class VestsScreen extends GateScreen implements WithSettings, ReloadTimerInterface
{
    use WithReloadTimer;

    private VestsSettings $settings;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Vesty', context: 'screens', domain: 'gate');
    }

    public static function getDescription() : string {
        return lang('Obrazovka zobrazující přiřazené vesty před hrou.', context: 'screens.description', domain: 'gate');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.vests';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/vests.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        return new VestsSettings(isset($data['time']) ? (int) $data['time'] : null);
    }

    public function getSettings() : VestsSettings {
        if (!isset($this->settings)) {
            $this->settings = new VestsSettings();
        }
        return $this->settings;
    }

    public function setSettings(GateSettings $settings) : static {
        if (!($settings instanceof VestsSettings)) {
            throw new InvalidArgumentException(
              '$settings must be an instance of '.VestsSettings::class.', '.$settings::class.' provided.'
            );
        }
        $this->settings = $settings;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $game = $this->game;

        if (!isset($game)) {
            return $this->respond(new ErrorResponse('Cannot show screen without game.'), 412);
        }

        if ($this->reloadTime < 0) {
            $this->setReloadTime($this->getReloadTimer());
        }

        // Calculate current screen hash (for caching)
        $data = [];
        foreach ($game->players as $player) {
            $data[$player->vest] = $player->color.','.$player->name.','.$player->user?->getCode();
        }
        ksort($data);
        $screenHash = md5(implode(';', array_map(static fn($key) => $key.':'.$data[$key], array_keys($data))));

        return $this
          ->view(
            'gate/screens/vests',
            [
              'game'       => $game,
              'screenHash' => $screenHash,
              'vests'      => Vest::getForSystem($game::SYSTEM),
              'addJs'      => ['gate/vests.js'],
              'addCss'     => ['gate/vests.css'],
            ]
          );
    }
}
