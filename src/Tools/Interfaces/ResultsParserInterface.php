<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools\Interfaces;

use App\GameModels\Game\Game;
use Lsr\Exceptions\FileException;

/**
 * Interface for all result parsers
 *
 * @template G of Game
 */
interface ResultsParserInterface
{
    /**
     * @param string $fileName
     *
     * @return $this
     * @throws FileException
     */
    public function setFile(string $fileName): static;

    /**
     * @param string $contents
     *
     * @return $this
     */
    public function setContents(string $contents): static;

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
     * @param string $contents
     * @return bool True if this parser can parse this game file
     * @pre File exists
     * @pre File is readable
     *
     */
    public static function checkFile(string $fileName = '', string $contents = ''): bool;
}
