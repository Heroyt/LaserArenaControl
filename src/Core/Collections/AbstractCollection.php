<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Collections;

use App\Core\Interfaces\CollectionInterface;
use App\Exceptions\InvalidCollectionClassException;
use InvalidArgumentException;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

/**
 * @template T of Model
 * @implements CollectionInterface<T>
 * @extends ModelCollection<T>
 */
abstract class AbstractCollection extends ModelCollection implements CollectionInterface
{
    /** @var string Type of collection's data */
    protected string $type;

    /**
     * Create a new collection from array of data
     *
     * @param  T[]  $array
     *
     * @return static
     */
    public static function fromArray(array $array) : AbstractCollection {
        return new (static::class)($array);
    }

    /**
     * Get all collection's data as an array
     *
     * @return T[]
     */
    public function getAll() : array {
        return $this->models;
    }

    /**
     * Add new data to collection
     *
     * @details Checks value's type and uniqueness
     *
     * @param  T  $value
     * @param  int  $key
     *
     * @return $this
     */
    public function set(Model $value, int $key) : AbstractCollection {
        if (!$this->checkType($value)) {
            throw new InvalidCollectionClassException(
              'Class '.get_class($value).' cannot be added to collection of type '.$this->type
            );
        }
        if (!empty($this->models[$key])) {
            throw new InvalidArgumentException('Cannot set data for key: '.$key.' - the key is not available.');
        }
        if (!$this->contains($value)) {
            $this->models[$key] = $value;
        }
        return $this;
    }

    /**
     * Checks value's type before adding to the collection
     *
     * @param  T  $value
     *
     * @return bool
     */
    protected function checkType(Model $value) : bool {
        if (!isset($this->type)) {
            $this->type = get_class($value);
            return true;
        }
        return is_subclass_of($value, $this->type);
    }

    /**
     * Checks whether the given model already exists in collection
     *
     * @param  T  $model
     *
     * @return bool
     */
    public function contains(Model $model) : bool {
        if (in_array($model, $this->models, true)) {
            return true;
        }
        return false;
    }

    /**
     * @param  int  $key
     *
     * @return T|null
     */
    public function get(int $key) : ?Model {
        if (empty($this->models[$key])) {
            return null;
        }
        return $this->models[$key];
    }

    /**
     * Get collection's model type
     *
     * @return string
     */
    public function getType() : string {
        $first = $this->first();
        return isset($first) ? get_class($first) : $this->type;
    }

    /**
     * Sort collection's data using a callback function
     *
     * @param  callable(Model $ą, Model $b):int  $callback
     *
     * @return $this
     */
    public function sort(callable $callback) : AbstractCollection {
        usort($this->models, $callback);
        return $this;
    }
}
