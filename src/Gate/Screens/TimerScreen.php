<?php

namespace App\Gate\Screens;

use App\Core\App;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Models\GateScreenModel;
use App\Gate\Settings\GateSettings;
use App\Gate\Settings\TimerSettings;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<TimerSettings>
 */
class TimerScreen extends GateScreen implements WithSettings
{
    private TimerSettings $settings;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Časovač', domain: 'gate', context: 'screens');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka, která automaticky cyklí podle časovače.',
          domain : 'gate',
          context: 'screens.description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.timer';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/timer.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        $children = [];

        // Process screens
        $screensData = array_merge($data['screen'] ?? [], $data['new-screen'] ?? []);
        bdump($screensData);
        foreach ($screensData as $screenData) {
            $screenModel = new GateScreenModel();

            if (isset($screenData['type']) && (!isset($screenModel->screenSerialized) || $screenData['type'] !== $screenModel->screenSerialized)) {
                // @phpstan-ignore-next-line
                $screenModel->setScreen(App::getService($screenData['type']));
            }
            if (isset($screenData['trigger'])) {
                $screenModel->setTrigger(ScreenTriggerType::from($screenData['trigger']));
            }
            if (isset($screenData['trigger_value']) && $screenModel->trigger === ScreenTriggerType::CUSTOM) {
                $screenModel->setTriggerValue($screenData['trigger_value']);
            }
            if (isset($screenData['settings']) && ($screen = $screenModel->getScreen()) instanceof WithSettings) {
                $screenModel->setSettings($screen::buildSettingsFromForm($screenData['settings']));
            }

            $children[] = $screenModel;
        }

        bdump($children);

        return new TimerSettings(
          $children,
          (int) ($data['timer'] ?? 60),
        );
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $screens = $this->getSettings()->children;

        $now = time();
        $activeScreen = (int) floor($now / $this->getSettings()->timer) % count($screens);
        // Remaining time for current screen to remain active + 2 seconds of buffer
        $timeRemaining = ($now % $this->getSettings()->timer) + 2;

        $screenModel = $screens[$activeScreen];
        $screen = $screenModel->getScreen()
          ->setReloadTime($timeRemaining)
          ->setGame($this->game)
                              ->setParams($this->params);
        if ($screen instanceof WithSettings) {
            $screen->setSettings($screenModel->getSettings());
        }

        return $screen->run();
    }

    /**
     * @inheritDoc
     */
    public function getSettings() : TimerSettings {
        if (!isset($this->settings)) {
            $this->settings = new TimerSettings();
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
