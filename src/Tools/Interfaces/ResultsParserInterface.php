<?php

namespace App\Tools\Interfaces;

use App\Models\Game\Game;

interface ResultsParserInterface
{

	public function parse() : Game;
}