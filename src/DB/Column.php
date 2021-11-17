<?php

namespace App\DB;

class Column
{

	public const TYPE_VARCHAR  = 'varchar';
	public const TYPE_INT      = 'int';
	public const TYPE_TINYINT  = 'tinyint';
	public const TYPE_ENUM     = 'enum';
	public const TYPE_TEXT     = 'text';
	public const TYPE_DATETIME = 'datetime';
	public const TYPE_DATE     = 'date';
	public const TYPE_TIME     = 'time';

	public int $size;
	/** @var null|int|float|string */
	public mixed  $default       = []; // Array is an invalid value
	public string $comment;
	public bool   $unsigned      = false;
	public bool   $nullable      = false;
	public bool   $autoIncrement = false;
	public array  $values        = [];

	protected bool $dbSet = false;

	public function __construct(
		public string $name,
		public string $type,
	) {
	}

	public function size(int $size) : static {
		$this->size = $size;
		return $this;
	}

	public function default(mixed $value) : static {
		$this->default = $value;
		return $this;
	}

	public function comment(string $comment) : static {
		$this->comment = $comment;
		return $this;
	}

	public function unsigned() : static {
		$this->unsigned = true;
		return $this;
	}

	public function nullable() : static {
		$this->nullable = true;
		return $this;
	}

	public function autoIncrement() : static {
		$this->autoIncrement = true;
		return $this;
	}

	/**
	 * @param string[] $values
	 *
	 * @return Column
	 */
	public function values(array $values) : Column {
		$this->values = $values;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isDbSet() : bool {
		return $this->dbSet;
	}

	/**
	 * @return Column
	 */
	public function dbSet() : Column {
		$this->dbSet = true;
		return $this;
	}

	public function getQuery() : string {
		$query = '`'.$this->name.'` '.$this->type;
		if (in_array($this->type, [$this::TYPE_INT, $this::TYPE_VARCHAR, $this::TYPE_TINYINT], true)) {
			$query .= '('.$this->size.')';
		}
		else if ($this->type === $this::TYPE_ENUM) {
			$query .= "('".implode("','", $this->values)."')";
		}
		if ($this->unsigned) {
			$query .= ' unsigned';
		}
		if (!$this->nullable) {
			$query .= ' NOT NULL';
		}
		if (is_null($this->default)) {
			$query .= ' DEFAULT NULL';
		}
		else if (is_numeric($this->default)) {
			$query .= ' DEFAULT '.$this->default;
		}
		else if (is_string($this->default)) {
			$query .= " DEFAULT '{$this->default}'";
		}
		if ($this->autoIncrement) {
			$query .= ' AUTO_INCREMENT';
		}
		if (!empty($this->comment)) {
			$query .= ' COMMENT \''.$this->comment.'\'';
		}
		return $query;
	}
}