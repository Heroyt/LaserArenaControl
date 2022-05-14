<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core\Interfaces;

use App\Core\AbstractModel;
use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

interface CollectionInterface extends ArrayAccess, JsonSerializable, Countable, Iterator
{

	/**
	 * Create a new collection from array of data
	 *
	 * @param AbstractModel[] $array
	 *
	 * @return CollectionInterface
	 */
	public static function fromArray(array $array) : CollectionInterface;

	/**
	 * Get all collection's data as an array
	 *
	 * @return AbstractModel[]
	 */
	public function getAll() : array;

	public function query() : CollectionQueryInterface;

	/**
	 * Add new data to collection
	 *
	 * @param AbstractModel ...$values
	 *
	 * @return CollectionInterface
	 */
	public function add(AbstractModel ...$values) : CollectionInterface;

	/**
	 * Checks whether the given model already exists in collection
	 *
	 * @param AbstractModel $model
	 *
	 * @return bool
	 */
	public function contains(AbstractModel $model) : bool;

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
	 * @return AbstractModel|null
	 */
	public function first() : ?AbstractModel;

}