<?php

namespace App\Core\Collections;

use App\Core\Interfaces\CollectionInterface;
use App\Core\Interfaces\CollectionQueryFilterInterface;

class CollectionCompareFilter implements CollectionQueryFilterInterface
{

	public function __construct(
		public string     $property,
		public Comparison $comparison,
		public mixed      $value,
	) {
	}

	public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface {
		$remove = [];
		foreach ($collection as $key => $value) {
			if (property_exists($value, $this->property)) {
				switch ($this->comparison) {
					case Comparison::GREATER:
						if ($value->{$this->property} <= $this->value) {
							$remove[] = $key;
						}
						break;
					case Comparison::LESS:
						if ($value->{$this->property} >= $this->value) {
							$remove[] = $key;
						}
						break;
					case Comparison::EQUAL:
						if ($value->{$this->property} !== $this->value) {
							$remove[] = $key;
						}
						break;
					case Comparison::NOT_EQUAL:
						if ($value->{$this->property} === $this->value) {
							$remove[] = $key;
						}
						break;
					case Comparison::GREATER_EQUAL:
						if ($value->{$this->property} < $this->value) {
							$remove[] = $key;
						}
						break;
					case Comparison::LESS_EQUAL:
						if ($value->{$this->property} > $this->value) {
							$remove[] = $key;
						}
						break;
				}
			}
		}
		foreach ($remove as $key) {
			unset($collection[$key]);
		}
		return $this;
	}
}