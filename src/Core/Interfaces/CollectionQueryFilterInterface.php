<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Interfaces;

use Lsr\Orm\Model;

/**
 * @template T of Model
 */
interface CollectionQueryFilterInterface
{
    /**
     * @param  CollectionInterface<T>  $collection
     *
     * @return CollectionQueryFilterInterface<T>
     */
    public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface;
}
