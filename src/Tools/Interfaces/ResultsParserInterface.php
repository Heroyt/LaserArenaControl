<?php

namespace App\Tools\Interfaces;

use App\Models\Game\Game;

interface ResultsParserInterface
{

	/**
	 * Parse a game results file and return a parsed object
	 *
	 * @return Game
	 */
	public function parse() : Game;
}