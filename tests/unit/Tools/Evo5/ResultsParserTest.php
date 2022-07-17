<?php

namespace TestCases\unit\Tools\Evo5;

use App\Exceptions\ResultsParseException;
use App\Tools\Evo5\ResultsParser;
use Lsr\Exceptions\FileException;
use PHPUnit\Framework\TestCase;
use const unit\Tools\Evo5\ROOT;

class ResultsParserTest extends TestCase
{

	public function getFiles() : array {
		$files = array_merge(glob(ROOT.'results-test/*_archive.game'), glob(ROOT.'results/????.game'));
		return array_map(static function(string $fName) {
			return [$fName];
		}, $files);
	}

	public function getFilesError() : array {
		$files = glob(ROOT.'results-test/????_error.game');
		return array_map(static function(string $fName) {
			return [$fName];
		}, $files);
	}

	/**
	 * @param string $file
	 *
	 * @throws FileException
	 * @dataProvider getFiles
	 */
	public function testParser(string $file) : void {
		$parser = new ResultsParser($file);
		$game = $parser->parse();
		// Validate parsed game data
		$dataFile = str_replace('.game', '.out.json', $file);
		$data = json_decode(file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);
		self::assertEquals(
			$data,
			json_decode(json_encode($game, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR)
		);
	}

	/**
	 * @dataProvider getFilesError
	 *
	 * @param string $file
	 *
	 * @throws FileException
	 */
	public function testParserError(string $file) : void {
		$parser = new ResultsParser($file);
		$this->expectException(ResultsParseException::class);
		$game = $parser->parse();
	}

	public function testUnknownFile() : void {
		$this->expectException(FileException::class);
		$parser = new ResultsParser(ROOT.'invalidFile');
	}

}