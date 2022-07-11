<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Tools\Interfaces;

use App\GameModels\Game\Game;

/**
 * Interface for all result parsers
 */
interface ResultsParserInterface
{

	/**
	 * Parse a game results file and return a parsed object
	 *
	 * @return Game
	 */
	public function parse() : Game;
}