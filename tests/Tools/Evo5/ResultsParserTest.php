<?php

namespace Tools\Evo5;

use App\Exceptions\FileException;
use App\Tools\Evo5\ResultsParser;
use PHPUnit\Framework\TestCase;

class ResultsParserTest extends TestCase
{

	public function getFiles() : array {
		$files = array_merge(glob(ROOT.'results/*_archive.game'), glob(ROOT.'results/????.game'));
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
		//print_r($game);
		self::assertTrue(true);
	}

}