<?php

namespace App\Tools\ResultParsing;

use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Services\PlayerProvider;
use App\Tools\Interfaces\ResultsParserInterface;
use Lsr\Exceptions\FileException;

/**
 * @template G of Game
 */
class ResultsParser
{

	/** @var ResultsParserInterface<G> */
	private ResultsParserInterface $parser;

	/**
	 * @param string         $fileName
	 * @param PlayerProvider $playerProvider
	 *
	 * @throws FileException
	 */
	public function __construct(
		protected string                  $fileName,
		protected readonly PlayerProvider $playerProvider,
	) {
		if (!file_exists($this->fileName) || !is_readable($this->fileName)) {
			throw new FileException('File "' . $this->fileName . '" does not exist or is not readable');
		}
	}

	/**
	 * Parse a given game file
	 *
	 * @return G
	 * @throws ResultsParseException
	 */
	public function parse(): Game {
		return $this->findParser()->parse();
	}

	/**
	 * @return ResultsParserInterface<G>
	 * @throws ResultsParseException
	 */
	private function findParser(): ResultsParserInterface {
		if (!isset($this->parser)) {
			$baseNamespace = 'App\\Tools\\ResultParsing\\';
			foreach (GameFactory::getSupportedSystems() as $system) {
				/** @var class-string<ResultsParserInterface<G>> $class */
				$class = $baseNamespace . ucfirst($system) . 'ResultsParser';
				if (class_exists($class) && $class::checkFile($this->fileName)) {
					$this->parser = new $class($this->fileName, $this->playerProvider);
					return $this->parser;
				}
			}
			throw new ResultsParseException('Cannot find parser for given results file: ' . $this->fileName);
		}
		return $this->parser;
	}
}