<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core\Collections;

use App\Core\Interfaces\CollectionInterface;
use App\Core\Interfaces\CollectionQueryFilterInterface;

class CollectionQueryFilter implements CollectionQueryFilterInterface
{

	public function __construct(
		public string $name,
		public array  $values = [],
		public bool   $method = false
	) {
	}

	public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface {
		$remove = [];
		foreach ($collection as $key => $model) {
			$modelValues = $this->method ? $model->{$this->name}() : $model->{$this->name};
			$filter = false;
			if (is_array($modelValues)) {
				// TODO: Compare arrays
			}
			else {
				$filter = in_array($modelValues, $this->values, false);
			}
			if (!$filter) {
				$remove[] = $key;
			}
		}
		foreach ($remove as $key) {
			unset($collection[$key]);
		}
		return $this;
	}

}