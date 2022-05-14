<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core\Interfaces;


interface CollectionQueryFilterInterface
{

	public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface;

}