<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core\Interfaces;

use App\Core\AbstractModel;

interface CollectionQueryInterface
{

	/**
	 * Add a new filter to filter data by
	 *
	 * @param string $param
	 * @param mixed  ...$values
	 *
	 * @return CollectionQueryInterface
	 */
	public function filter(string $param, mixed ...$values) : CollectionQueryInterface;

	/**
	 * Add any filter object
	 *
	 * @param CollectionQueryFilterInterface $filter
	 *
	 * @return CollectionQueryInterface
	 */
	public function addFilter(CollectionQueryFilterInterface $filter) : CollectionQueryInterface;

	/**
	 * Get the query's result
	 *
	 * @param bool $returnArray
	 *
	 * @return CollectionInterface|array
	 */
	public function get(bool $returnArray = false) : CollectionInterface|array;

	/**
	 * Get only the first result or null
	 *
	 * @return AbstractModel|null|mixed
	 */
	public function first() : mixed;

	/**
	 * Set a parameter to sort the by result
	 *
	 * @param string $param
	 *
	 * @return CollectionQueryInterface
	 */
	public function sortBy(string $param) : CollectionQueryInterface;

	/**
	 * Map the result to return an array of only given parameter
	 *
	 * @param string $param
	 *
	 * @return CollectionQueryInterface
	 */
	public function pluck(string $param) : CollectionQueryInterface;

	/**
	 * Add a map callback
	 *
	 * @param callable $callback
	 *
	 * @return CollectionQueryInterface
	 * @see array_map()
	 */
	public function map(callable $callback) : CollectionQueryInterface;

	/**
	 * Set sort direction in ascending order
	 *
	 * @return CollectionQueryInterface
	 */
	public function asc() : CollectionQueryInterface;

	/**
	 * Set sort direction in descending order
	 *
	 * @return CollectionQueryInterface
	 */
	public function desc() : CollectionQueryInterface;

}