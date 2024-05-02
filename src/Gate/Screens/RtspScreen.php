<?php

namespace App\Gate\Screens;

use App\Gate\Settings\GateSettings;
use App\Gate\Settings\RtspSettings;
use Psr\Http\Message\ResponseInterface;

/**
 * @implements WithSettings<RtspSettings>
 */
class RtspScreen extends GateScreen implements WithSettings
{

    private RtspSettings $settings;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('RTSP - kamery', context: 'gate.screens');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.rtsp';
    }

    /**
     * @inheritDoc
     */
    public static function getSettingsForm() : string {
        return 'gate/settings/rtsp.latte';
    }

    /**
     * @inheritDoc
     */
    public static function buildSettingsFromForm(array $data) : GateSettings {
        return new RtspSettings(
          array_filter(array_map('trim', explode("\n", $data['streams'] ?? ''))),
          (int) ($data['max-screens'] ?? 9),
        );
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        return $this->view(
          'gate/screens/rtsp',
          [
            'settings' => $this->getSettings(),
            'hash'     => md5(json_encode($this->settings, JSON_THROW_ON_ERROR)),
            'addCss'   => ['gate/rtsp.css'],
            'addJs'    => ['gate/rtsp.js'],
          ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getSettings() : RtspSettings {
        if (!isset($this->settings)) {
            $this->settings = new RtspSettings();
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