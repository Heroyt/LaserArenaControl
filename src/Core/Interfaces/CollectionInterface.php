<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Interfaces;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Lsr\Core\Models\Model;

interface CollectionInterface extends ArrayAccess, JsonSerializable, Countable, Iterator
{

	/**
	 * Create a new collection from array of data
	 *
	 * @param Model[] $array
	 *
	 * @return CollectionInterface
	 */
	public static function fromArray(array $array) : CollectionInterface;

	/**
	 * Get all collection's data as an array
	 *
	 * @return Model[]
	 */
	public function getAll() : array;

	public function query() : CollectionQueryInterface;

	/**
	 * Add new data to collection
	 *
	 * @param Model ...$values
	 *
	 * @return CollectionInterface
	 */
	public function add(Model ...$values) : CollectionInterface;

	/**
	 * Checks whether the given model already exists in collection
	 *
	 * @param Model $model
	 *
	 * @return bool
	 */
	public function contains(Model $model) : bool;

	/**
	 * Get collection's model type
	 *
	 * @return string
	 */
	public function getType() : string;

	/**
	 * Sort collection's data using a callback function
	 *
	 * @param callable $callback
	 *
	 * @return CollectionInterface
	 */
	public function sort(callable $callback) : CollectionInterface;

	/**
	 * Get first object in collection
	 *
	 * @return Model|null
	 */
	public function first() : ?Model;

}