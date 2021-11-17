<?php

namespace App\Core;

use App\Core\Interfaces\InsertExtendInterface;
use App\Exceptions\ModelNotFoundException;
use App\Exceptions\ValidationException;
use App\Logging\DirectoryCreationException;
use App\Logging\Logger;
use App\Tools\Strings;
use ArrayAccess;
use Dibi\Exception;
use Dibi\Row;
use JsonSerializable;

abstract class AbstractModel implements JsonSerializable, ArrayAccess
{

	public const TABLE       = '';
	public const PRIMARY_KEY = 'id';

	public const DEFINITION = [

	];

	public ?int      $id = null;
	protected Row    $row;
	protected Logger $logger;

	/**
	 * @param int|null $id    DB model ID
	 * @param Row|null $dbRow Prefetched database row
	 *
	 * @throws ModelNotFoundException|DirectoryCreationException
	 */
	public function __construct(?int $id = null, ?Row $dbRow = null) {
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
	 * @return bool
	 * @throws ValidationException
	 */
	public function save() : bool {
		return isset($this->id) ? $this->update() : $this->insert();
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function update() : bool {
		$this->logger->info('Updating model - '.$this->id);
		try {
			DB::update($this::TABLE, $this->getQueryData(), ['%n = %i', $this::PRIMARY_KEY, $this->id]);
		} catch (Exception $e) {
			$this->logger->error('Error running update query: '.$e->getMessage());
			$this->logger->debug('Query: '.$e->getSql());
			$this->logger->debug('Trace: '.$e->getTraceAsString());
			return false;
		}
		return true;
	}

	/**
	 * Get an array of values for DB to insert/update. Values are validated.
	 *
	 * @return array
	 * @throws ValidationException
	 */
	public function getQueryData() : array {
		$data = [];
		foreach ($this::DEFINITION as $property => $definition) {
			$validators = $definition['validators'] ?? [];
			if (!isset($this->$property) && !in_array('required', $validators, true)) {
				if (isset($definition['default'])) {
					$data[Strings::toCamelCase($property)] = $definition['default'];
				}
				continue;
			}
			if (!empty($validators)) {
				ModelValidator::validateValue($this->$property, $validators);
			}
			if ($this->$property instanceof InsertExtendInterface) {
				($this->$property)->addQueryData($data);
			}
			else {
				$data[Strings::toSnakeCase($property)] = $this->$property;
			}
		}
		return $data;
	}

	/**
	 * @return bool
	 * @throws ValidationException
	 */
	public function insert() : bool {
		$this->logger->info('Inserting new model');
		try {
			DB::insert($this::TABLE, $this->getQueryData());
			$this->id = DB::getInsertId();
		} catch (Exception $e) {
			$this->logger->error('Error running insert query: '.$e->getMessage());
			$this->logger->debug('Query: '.$e->getSql());
			$this->logger->debug('Trace: '.$e->getTraceAsString());
			return false;
		}
		if (empty($this->id)) {
			$this->logger->error('Insert query passed, but ID was not returned.');
			return false;
		}
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function jsonSerialize() : array {
		$vars = get_object_vars($this);
		if (isset($vars['row'])) {
			unset($vars['row']);
		}
		return $vars;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetGet($offset) {
		if ($this->offsetExists($offset)) {
			return $this->$offset;
		}
		return null;
	}

	/**
	 * @inheritdoc
	 */
	public function offsetExists($offset) : bool {
		return property_exists($this, $offset);
	}

	/**
	 * @inheritdoc
	 */
	public function offsetSet($offset, $value) : void {
		if ($this->offsetExists($offset)) {
			$this->$offset = $value;
		}
	}

	/**
	 * @inheritdoc
	 */
	public function offsetUnset($offset) : void {
		// Do nothing
	}

}