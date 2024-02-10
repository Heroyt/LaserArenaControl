<?php

namespace TestCases\unit\Tools\Evo5;

use App\Core\App;
use App\Exceptions\ResultsParseException;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use App\Services\PlayerProvider;
use App\Tools\ResultParsing\Evo5\ResultsParser;
use Lsr\Exceptions\FileException;
use Lsr\Helpers\Tracy\DbTracyPanel;
use PHPUnit\Framework\TestCase;

class ResultsParserTest extends TestCase
{

	/**
	 * @return string[][]
	 */
	public function getFiles() : array {
		/** @var string[] $files */
		$files = glob(ROOT.'results-test/*_archive.game');
		return array_map(static function(string $fName) {
			return [$fName];
		}, $files);
	}

	/**
	 * @return string[][]
	 */
	public function getFilesError() : array {
		/** @var string[] $files */
		$files = glob(ROOT.'results-test/????_error.game');
		return array_map(static function(string $fName) {
			return [$fName];
		}, $files);
	}

	/**
	 * @param string $file
	 *
	 * @dataProvider getFiles
	 */
	public function testParserAndDbSave(string $file) : void {
		$parser = new ResultsParser($file, App::getContainer()->getByType(PlayerProvider::class));
		$game = $parser->parse();

		if (!$game->isFinished()) {
			// Skip if not finished
			self::assertTrue(true);
			return;
		}

		// Test DB save
		/* @phpstan-ignore-next-line */
		self::assertTrue($game->save(), 'Game save failed for file: '.$file.' - '.last(DbTracyPanel::$events)?->message);
		self::assertNotNull($game->id, 'Game save failed for file: '.$file);

		// Test if all players have been inserted into DB
		/** @var Player $player */
		foreach ($game->getPlayers() as $player) {
			self::assertNotNull($player->id, 'Player save failed for file: '.$file);
		}
		// Test if all teams have been inserted into DB
		/** @var Team $team */
		foreach ($game->getTeams() as $team) {
			self::assertNotNull($team->id, 'Team save failed for file: '.$file);
		}

		// Get game from DB
		$game2 = new Game($game->id);
		self::assertEquals(
		/* @phpstan-ignore-next-line */
			json_decode(json_encode($game->getQueryData())),
			/* @phpstan-ignore-next-line */
			json_decode(json_encode($game2->getQueryData())),
			'Game data got from DB is not the same: '.$file
		);

		// Test all players
		foreach ($game->getPlayers() as $player) {
			// Get the same player from DB
			$player2 = $game2->getPlayers()->get($player->vest);

			self::assertNotNull($player2, 'Player does not exist in DB: '.$file);
			self::assertEquals(
			/* @phpstan-ignore-next-line */
				json_decode(json_encode($player->getQueryData())),
				/* @phpstan-ignore-next-line */
				json_decode(json_encode($player2->getQueryData())),
				'Player data got from DB is not the same: '.$file
			);

			// Test if all players have a team assigned
			if (!$game->mode?->isSolo()) {
				$team = $player->getTeam();
				$team2 = $player2?->getTeam();
				self::assertNotNull($team);
				self::assertSame($team2?->color, $team?->color);
			}
		}

		// Test all teams
		/** @var Team $team */
		foreach ($game->getTeams() as $team) {
			// Get the same team from DB
			$team2 = $game2->getTeams()->get($team->color);
			self::assertNotNull($team2, 'Team does not exist in DB: '.$file);
			self::assertEquals(
			/* @phpstan-ignore-next-line */
				json_decode(json_encode($team->getQueryData())),
				/* @phpstan-ignore-next-line */
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
		$parser = new ResultsParser($file, App::getContainer()->getByType(PlayerProvider::class));
		$this->expectException(ResultsParseException::class);
		$game = $parser->parse();
	}

	public function testUnknownFile() : void {
		$this->expectException(FileException::class);
		$parser = new ResultsParser(ROOT.'invalidFile', App::getContainer()->getByType(PlayerProvider::class));
	}

}