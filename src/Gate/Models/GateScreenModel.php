<?php

namespace App\Gate\Models;

use App\Core\App;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use App\Gate\Settings\GateSettings;
use App\Models\BaseModel;
use Lsr\Caching\Cache;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use OpenApi\Attributes as OA;

/**
 *
 */
#[PrimaryKey('id_screen'), OA\Schema]
class GateScreenModel extends BaseModel
{
    public const string TABLE = 'gate_screens';

    #[ManyToOne, OA\Property]
    public GateType $gate;
    public int $order = 0;

    public string $screenSerialized;
    public ?string $settingsSerialized = null;

    public ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT;
    public ?string $triggerValue = null;

    private GateScreen $screen;
    private ?GateSettings $settings = null;

    public static function createFromScreen(
      GateScreen        $screen,
      ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT
    ) : GateScreenModel {
        $model = new self();
        $model->setScreen($screen)->setTrigger($trigger);
        if ($screen instanceof WithSettings) {
            $model->setSettings($screen->getSettings());
        }
        return $model;
    }

    public function setTrigger(ScreenTriggerType $trigger) : GateScreenModel {
        $this->trigger = $trigger;
        return $this;
    }

    public function getSettings() : ?GateSettings {
        if (!isset($this->settings) && isset($this->settingsSerialized)) {
            $settings = igbinary_unserialize($this->settingsSerialized);
            $this->settings = $settings === false ? null : $settings;
        }
        // @phpstan-ignore-next-line
        return $this->settings;
    }

    public function setSettings(GateSettings $settings) : GateScreenModel {
        $this->settings = $settings;
        $this->settingsSerialized = igbinary_serialize($settings);
        return $this;
    }

    public function __serialize() : array {
        return [
          'id'                  => $this->id,
          'gate'                => isset($this->gate) ? $this->gate->id : null,
          'order'               => $this->order,
          'screen_serialized'   => $this->screenSerialized,
          'settings_serialized' => $this->settingsSerialized,
          'trigger'             => $this->trigger,
          'triggerValue'        => $this->triggerValue,
        ];
    }

    public function __unserialize(array $data) : void {
        if (isset($data['gate'])) {
            $this->gate = GateType::get($data['gate']);
        }
        $this->id = $data['id'] ?? null;
        $this->order = $data['order'] ?? 0;
        $this->triggerValue = $data['triggerValue'] ?? null;
        $this->screenSerialized = $data['screen_serialized'];
        $this->settingsSerialized = $data['settings_serialized'];
        $this->trigger = $data['trigger'];
    }

    public function getScreen() : GateScreen {
        if (!isset($this->screen)) {
            // @phpstan-ignore-next-line
            $this->screen = App::getService($this->screenSerialized);
            if (isset($this->trigger)) {
                $this->screen->setTrigger($this->trigger);
            }
        }
        // @phpstan-ignore-next-line
        return $this->screen;
    }

    public function setScreen(GateScreen $screen) : GateScreenModel {
        $this->screen = $screen;
        $this->screenSerialized = $screen::getDiKey();
        return $this;
    }

    public function setGate(GateType $gate) : GateScreenModel {
        $this->gate = $gate;
        return $this;
    }

    public function setOrder(int $order) : GateScreenModel {
        $this->order = $order;
        return $this;
    }

    public function setScreenSerialized(string $screenSerialized) : GateScreenModel {
        $this->screenSerialized = $screenSerialized;
        return $this;
    }

    public function setSettingsSerialized(?string $settingsSerialized) : GateScreenModel {
        $this->settingsSerialized = $settingsSerialized;
        return $this;
    }

    public function setTriggerValue(?string $triggerValue) : GateScreenModel {
        $this->triggerValue = $triggerValue;
        return $this;
    }

    public function clearCache() : void {
        if (isset($this->gate)) {
            /** @var Cache $cache */
            $cache = App::getService('cache');
            $cache->remove('gateType.'.$this->gate->id.'.screens');
            $cache->clean([$cache::Tags => [$this->gate::TABLE.'/'.$this->gate->id.'/relations',]]);
        }
        parent::clearCache();
    }
}
