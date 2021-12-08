<?php

namespace App\Core\Interfaces;

use App\Core\Routing\RouteInterface;

interface RequestInterface
{

	public function __construct(array|string $query);

	public function handle() : void;

	public function getRoute() : ?RouteInterface;

}