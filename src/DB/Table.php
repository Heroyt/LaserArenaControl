<?php

namespace App\DB;

use App\Core\DB;

class Table
{

	/** @var Column[] */
	public array $columns = [];

	public function __construct(
		public string $name,
	) {
	}

	public static function get(string $name) : ?Table {
		$definition = DB::getConnection()->query('SHOW CREATE TABLE %n', $name)->fetch();
		if (isset($definition)) {
			var_dump($definition->toArray());
			return SQLParser::parseTableCreateQuery($definition->toArray()['Create Table']);
		}
		return null;
	}

	public function int(string $name, int $size) : Column {
		$column = new Column($name, Column::TYPE_INT);
		$column->size($size);
		$this->columns[] = $column;
		return $column;
	}

	public function varchar(string $name, int $size) : Column {
		$column = new Column($name, Column::TYPE_VARCHAR);
		$column->size($size);
		$this->columns[] = $column;
		return $column;
	}

	public function getCreateQuery(string $flag = '') : string {
		$columns = [];
		foreach ($this->columns as $column) {
			$columns[] = $column->getQuery();
		}
		$query = 'CREATE'.(empty($flag) ? '' : ' '.$flag).' TABLE `'.$this->name.'` ('.PHP_EOL.implode(','.PHP_EOL, $columns).PHP_EOL.');';
		return $query;
	}

}