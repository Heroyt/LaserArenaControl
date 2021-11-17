<?php

namespace App\DB;

class SQLParser
{

	public static function parseTableCreateQuery(string $query) : Table {
		preg_match('/CREATE TABLE `([^`]+)` \(([^;]+)\)[^();]*/m', $query, $matches);
		print_r($matches);
		$name = $matches[1];
		$table = new Table($name);
		$definition = explode(PHP_EOL, trim($matches[2]));
		print_r($definition);
		foreach ($definition as $line) {
			if (preg_match("/`([a-zA-z\-_0-9]+)` (([a-z]+)(?:\((\d+)\))?(?:\(([',A-Za-z]+)\))?(?: (unsigned))?) ?(NOT NULL)? ?(DEFAULT (?:'[^']+')?(?:NULL)?(?:\d+)?)? ?(AUTO_INCREMENT)? ?(COMMENT '[^']+')?,/", $line, $matches) === false) {
				continue;
			}
			print_r($matches);
			if (count($matches) === 0) {
				continue;
			}
			$column = new Column($matches[1], $matches[3]);
			$column->dbSet();
			switch ($column->type) {
				case Column::TYPE_INT:
				case Column::TYPE_TINYINT:
					if (($matches[6] ?? '') === 'unsigned') {
						$column->unsigned();
					}
					if (($matches[9] ?? '') === 'AUTO_INCREMENT') {
						$column->autoIncrement();
					}
				case Column::TYPE_VARCHAR:
					$column->size($matches[4]);
					break;
				case Column::TYPE_ENUM:
					preg_match_all("/'([A-Za-z]+)'/", $matches[5] ?? '', $matches);
					$values = [];
					foreach ($matches as $match) {
						$values[] = $match[0];
					}
					echo 'Values: '.PHP_EOL;
					print_r($values);
					$column->values($values);
					break;
			}
			if (($matches[7] ?? '') !== 'NOT NULL') {
				$column->nullable();
			}
			$table->columns[] = $column;
		}
		print_r($table);
		return $table;
	}

}