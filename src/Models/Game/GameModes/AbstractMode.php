<?php

namespace App\Models\Game\GameModes;

use App\Core\AbstractModel;
use App\Core\DB;
use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ModelNotFoundException;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;
use App\Models\Factory\GameModeFactory;
use App\Models\Game\ModeSettings;
use App\Tools\Strings;
use Dibi\Row;

abstract class AbstractMode extends AbstractModel implements InsertExtendInterface
{

	public const TABLE       = 'game_modes';
	public const PRIMARY_KEY = 'id_mode';

	public const DEFINITION = [
		'name'        => ['validators' => ['required']],
		'description' => [],
		'type'        => [],
		'settings'    => ['class' => ModeSettings::class, 'initialize' => true],
	];

	public const TYPE_SOLO = 0;
	public const TYPE_TEAM = 1;

	public string       $name        = '';
	public ?string      $description = '';
	public int          $type        = self::TYPE_TEAM;
	public ModeSettings $settings;

	/**
	 * @param int|null $id
	 * @param Row|null $dbRow
	 *
	 * @throws ModelNotFoundException
	 * @throws DirectoryCreationException
	 * @noinspection PhpMissingParentConstructorInspection
	 * @noinspection MagicMethodsValidityInspection
	 */
	public function __construct(public ?int $id = null, ?Row $dbRow = null) {
		if (!isset(self::$instances[$this::TABLE])) {
			self::$instances[$this::TABLE] = [];
		}
		if (isset($id) && !empty($this::TABLE)) {
			if (!isset($dbRow)) {
				/** @noinspection CallableParameterUseCaseInTypeContextInspection */
				$dbRow = DB::select($this::TABLE, '*')->where('%n = %i', $this::PRIMARY_KEY, $id)->fetch();
			}
			if (!isset($dbRow)) {
				throw new ModelNotFoundException(get_class($this).' model of ID '.$id.' was not found.');
			}
			$this->row = $dbRow;
			foreach ($dbRow as $key => $val) {
				if ($key === $this::PRIMARY_KEY) {
					$this->id = $val;
				}
				if (property_exists($this, $key)) {
					if ($key === 'type') {
						$val = $val === 'TEAM' ? $this::TYPE_TEAM : $this::TYPE_SOLO;
					}
					$this->$key = $val;
					continue;
				}
				$key = Strings::toCamelCase($key);
				if (property_exists($this, $key)) {
					$this->$key = $val;
				}
			}
			foreach ($this::DEFINITION as $key => $definition) {
				$className = $definition['class'] ?? '';
				if (property_exists($this, $key) && !empty($className)) {
					$implements = class_implements($className);
					if (isset($implements[InsertExtendInterface::class])) {
						$this->$key = $className::parseRow($this->row);
					}
				}
			}
			self::$instances[$this::TABLE][$this->id] = $this;
		}
		else {
			foreach ($this::DEFINITION as $key => $definition) {
				if (isset($definition['class']) && ($definition['initialize'] ?? false)) {
					$className = $definition['class'];
					$this->$key = new $className();
				}
			}
		}
		$this->logger = new Logger(LOG_DIR.'models/', $this::TABLE);
	}

	/**
	 * @param Row $row
	 *
	 * @return InsertExtendInterface
	 * @throws GameModeNotFoundException
	 */
	public static function parseRow(Row $row) : InsertExtendInterface {
		return GameModeFactory::getById($row->id_mode ?? 0);
	}

	public function getQueryData() : array {
		$data = parent::getQueryData();
		$data['type'] = $data['type'] === $this::TYPE_TEAM ? 'TEAM' : 'SOLO';
		return $data;
	}

	public function addQueryData(array &$data) : void {
		$data['id_mode'] = $this->id;
	}

	public function isTeam() : bool {
		return $this->type === self::TYPE_TEAM;
	}

	public function isSolo() : bool {
		return $this->type === self::TYPE_SOLO;
	}


}