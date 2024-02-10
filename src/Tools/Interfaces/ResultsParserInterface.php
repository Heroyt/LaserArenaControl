<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\Interfaces;

use App\GameModels\Game\Game;

/**
 * Interface for all result parsers
 *
 * @template G of Game
 */
interface ResultsParserInterface
{

	/**
	 * Parse a game results file and return a parsed object
	 *
	 * @return G
	 */
	public function parse(): Game;

	/**
	 * Get result file pattern for lookup
	 *
	 * @return string
	 */
	public static function getFileGlob(): string;

	/**
	 * Check if given result file should be parsed by this parser.
	 *
	 * @param string $fileName
	 *
	 * @pre File exists
	 * @pre File is readable
	 *
	 * @return bool True if this parser can parse this game file
	 */
	public static function checkFile(string $fileName): bool;
}