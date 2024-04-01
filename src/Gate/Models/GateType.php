<?php

namespace App\Gate\Models;

use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Screens\GateScreen;
use App\Gate\Settings\GateSettings;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\OneToMany;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;
use Nette\Utils\Strings;
use OpenApi\Attributes as OA;

/**
 *
 */
#[PrimaryKey('id_gate'), OA\Schema]
class GateType extends Model
{

	public const TABLE = 'gate_types';

	#[OA\Property(example: 'Main gate')]
	public string $name;
	#[OA\Property(example: 'main_gate')]
	public string $slug = '';
	#[OA\Property(example: 'This is the main gate that is shown on the screen right in front of the arena.')]
	public ?string $description = null;
	#[OA\Property]
	public bool $locked = false;

	/** @var GateScreenModel[] */
	#[OneToMany(class: GateScreenModel::class, loadingType: LoadingType::LAZY)]
	public array $screens;

	public static function getBySlug(string $slug) : ?GateType {
		return static::query()->where('slug = %s', $slug)->first();
	}

	public function getQueryData() : array {
		$data = parent::getQueryData();
		if (empty($data['slug'])) {
			$data['slug'] = $this->getSlug();
		}
		return $data;
	}

	public function getSlug() : string {
		if (empty($this->slug)) {
			$this->slug = str_replace(' ', '-', strtolower(Strings::toAscii($this->name)));
			// Test uniqueness
			$count = static::query()->where('slug LIKE %like~', $this->slug)->count();
			if ($count > 0) {
				$this->slug .= '_'.$count;
			}
		}
		return $this->slug;
	}

	public function setSlug(string $slug) : GateType {
		$this->slug = $slug;
		return $this;
	}

	public function addScreenModel(GateScreenModel ...$screens) : GateType {
		// Load screens if needed
		$this->getScreens();

		foreach ($screens as $screen) {
			$this->screens[] = $screen;
			$screen->gate = $this;
		}

		return $this;
	}

	/**
	 * @return GateScreenModel[]
	 * @throws ValidationException
	 */
	public function getScreens() : array {
		if (!isset($this->screens)) {
			$this->screens = isset($this->id) ?
				GateScreenModel::query()
				               ->where('id_gate = %i', $this->id)
				               ->orderBy('order')
				               ->get() :
				[];
		}
		return $this->screens;
	}

	public function addScreen(
		GateScreen        $screen,
		?GateSettings     $settings = null,
		ScreenTriggerType $trigger = ScreenTriggerType::DEFAULT,
		int               $order = 0) : GateType {
		// Load screens if needed
		$this->getScreens();

		$screenModel = new GateScreenModel();
		$screenModel->setScreen($screen);
		if (isset($settings)) {
			$screenModel->setSettings($settings);
		}
		$screenModel->order = $order;
		$screenModel->trigger = $trigger;
		$this->screens[] = $screenModel;
		$screenModel->gate = $this;

		return $this;
	}

	public function save() : bool {
		return parent::save() && $this->saveScreens();
	}

	public function saveScreens() : bool {
		$success = true;

		if (isset($this->screens)) {
			foreach ($this->screens as $screen) {
				$success = $success && $screen->save();
			}
		}

		return $success;
	}

	public function setName(string $name) : GateType {
		$this->name = $name;
		return $this;
	}

	public function setDescription(?string $description) : GateType {
		$this->description = $description;
		return $this;
	}

	public function setLocked(bool $locked) : GateType {
		$this->locked = $locked;
		return $this;
	}


}