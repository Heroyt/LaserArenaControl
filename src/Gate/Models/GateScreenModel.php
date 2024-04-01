<?php

namespace App\Gate\Models;

use App\Core\App;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Screens\GateScreen;
use App\Gate\Settings\GateSettings;
use Lsr\Core\Models\Attributes\ManyToOne;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use OpenApi\Attributes as OA;

/**
 *
 */
#[PrimaryKey('id_screen'), OA\Schema]
class GateScreenModel extends Model
{

	public const TABLE = 'gate_screens';

	#[ManyToOne, OA\Property]
	public GateType $gate;
	public int $order = 0;

	public string $screenSerialized;
	public ?string $settingsSerialized = null;

	public ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT;
	public ?string $triggerValue = null;

	private GateScreen $screen;
	private ?GateSettings $settings = null;

	public function getScreen() : GateScreen {
		if (!isset($this->screen)) {
			// @phpstan-ignore-next-line
			$this->screen = App::getService($this->screenSerialized);
		}
		// @phpstan-ignore-next-line
		return $this->screen;
	}

	public function setScreen(GateScreen $screen) : GateScreenModel {
		$this->screen = $screen;
		$this->screenSerialized = $screen::getDiKey();
		return $this;
	}

	public function getSettings() : ?GateSettings {
		if (!isset($this->settings) && isset($this->settingsSerialized)) {
			// @phpstan-ignore-next-line
			$this->settings = igbinary_unserialize($this->settingsSerialized);
		}
		// @phpstan-ignore-next-line
		return $this->settings;
	}

	public function setSettings(GateSettings $settings) : GateScreenModel {
		$this->settings = $settings;
		$this->settingsSerialized = igbinary_serialize($settings);
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

	public function setTrigger(ScreenTriggerType $trigger) : GateScreenModel {
		$this->trigger = $trigger;
		return $this;
	}

	public function setTriggerValue(?string $triggerValue) : GateScreenModel {
		$this->triggerValue = $triggerValue;
		return $this;
	}



}