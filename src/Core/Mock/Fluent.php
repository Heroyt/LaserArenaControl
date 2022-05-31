<?php

namespace App\Core\Mock;

use Dibi\Result;
use Dibi\Row;

class Fluent extends \Dibi\Fluent
{

	/**
	 * Generates, executes SQL query and fetches the single row.
	 *
	 * @return Row|array|null
	 */
	public function fetch() : Row|array|null {
		return null;
	}

	public function fetchAssoc(string $assoc) : array {
		return [];
	}

	public function fetchAll(?int $offset = null, ?int $limit = null) : array {
		return [];
	}

	/**
	 * Generates and executes SQL query.
	 *
	 * @return Result|int|null  result set or number of affected rows
	 */
	public function execute(?string $return = null) : Result|int|null {
		return 0;
	}

}