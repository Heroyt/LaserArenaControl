<?php

namespace TestCases\unit\Tools\Evo5;

use App\Exceptions\ResultsParseException;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use App\Tools\Evo5\ResultsParser;
use Lsr\Exceptions\FileException;
use PHPUnit\Framework\TestCase;

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
		if (!$game->isFinished()) {
			self::assertTrue(true);
			return;
		}
		self::assertTrue($game->save(), 'Game save failed for file: '.$file);
		self::assertNotNull($game->id, 'Game save failed for file: '.$file);
		/** @var Player $player */
		foreach ($game->getPlayers() as $player) {
			self::assertNotNull($player->id, 'Player save failed for file: '.$file);
		}
		/** @var Team $team */
		foreach ($game->getTeams() as $team) {
			self::assertNotNull($team->id, 'Team save failed for file: '.$file);
		}
		$game2 = new Game($game->id);
		self::assertEquals(
			json_decode(json_encode($game->getQueryData())),
			json_decode(json_encode($game2->getQueryData())),
			'Game data got from DB is not the same: '.$file
		);
		foreach ($game->getPlayers() as $player) {
			$player2 = $game2->getPlayers()->get($player->vest);
			self::assertNotNull($player2, 'Player does not exist in DB: '.$file);
			self::assertEquals(
				json_decode(json_encode($player->getQueryData())),
				json_decode(json_encode($player2->getQueryData())),
				'Player data got from DB is not the same: '.$file
			);
		}
		/** @var Team $team */
		foreach ($game->getTeams() as $team) {
			$team2 = $game2->getTeams()->get($team->color);
			self::assertNotNull($team2, 'Team does not exist in DB: '.$file);
			self::assertEquals(
				json_decode(json_encode($team->getQueryData())),
				json_decode(json_encode($team2->getQueryData())),
				'Team data got from DB is not the same: '.$file
			);
		}
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