<?php

namespace App\Gate\Screens;

use App\Gate\Models\MusicGroupDto;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\MusicModeScreenLayout;
use App\Gate\Settings\MusicModeSettings;
use App\Models\MusicMode;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<MusicModeSettings>
 */
class MusicModesScreen extends GateScreen implements ReloadTimerInterface, WithSettings
{

    use WithReloadTimer;

    private MusicModeSettings $settings;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Seznam hudebních módů', context: 'gate.screens');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.music';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/music.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : MusicModeSettings {
        return new MusicModeSettings(
          MusicModeScreenLayout::tryFrom($data['layout'] ?? '') ?? MusicModeScreenLayout::EMPTY_SPACE,
        );
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $modes = [];
        foreach (MusicMode::getAll() as $music) {
            $group = $music->group ?? $music->name;
            $modes[$group] ??= new MusicGroupDto($group);
            $modes[$group]->music[] = $music;
        }
        return $this->view(
          'gate/screens/musicModes',
          [
            'musicModes' => $modes,
            'settings' => $this->getSettings(),
            'addCss'     => ['gate/musicModes.css'],
            'addJs'      => ['gate/musicModes.js'],
          ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettings() : MusicModeSettings {
        if (!isset($this->settings)) {
            $this->settings = new MusicModeSettings();
        }
        return $this->settings;
    }

    /**
     * @inheritDoc
     */
    public function setSettings(GateSettings $settings) : static {
        $this->settings = $settings;
        return $this;
    }
}