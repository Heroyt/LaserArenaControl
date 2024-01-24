<?php

namespace TestCases\integration\GameModels;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Services\PlayerProvider;
use App\Tools\Evo5\ResultsParser;
use Lsr\Core\Models\Model;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 *
 */
class ImportExportTest extends TestCase
{

	public const SKIP_FIELDS = [
		'fileTime',
		'id',
		'playerCount',
		'players',
		'teams',
		'game',
		'hitPlayers',
		'description',
		'mode',
		'importTime',
	];

	public static function setUpBeforeClass() : void {
		/** @var string[] $files */
		$files = glob(ROOT.'results-test/*_archive.game');
		foreach ($files as $file) {
			$parser = new ResultsParser($file, App::getContainer()->getByType(PlayerProvider::class));
			$parser->parse()->save();
		}
	}

	/**
	 * Get 20 random games
	 *
	 * @return Game[][]
	 */
	public function getRandomGames() : array {
		$data = [];
		$gameRows = GameFactory::queryGames(true)->orderBy('RAND()')->limit(10)->fetchAll();
		foreach ($gameRows as $row) {
			$game = GameFactory::getByCode($row->code);
			if (isset($game)) {
				$data[] = [$game];
			}
		}

		return $data;
	}

	/**
	 * @param Game $game
	 *
	 * @dataProvider getRandomGames
	 *
	 * @return void
	 */
	public function testExport(Game $game) : void {
		// Export to JSON and decode back into usable data
		$json = json_encode($game, JSON_THROW_ON_ERROR);
		/** @var array<string, mixed> $decoded */
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		// Test if the data contains all necessary information
		self::assertNotEmpty($decoded);
		self::assertTrue(isset($decoded['code']), 'Missing required field: "code"');
		self::assertTrue(isset($decoded['start']), 'Missing required field: "start"');
		self::assertTrue(isset($decoded['end']), 'Missing required field: "end"');
		if (isset($game->mode)) {
			self::assertTrue(isset($decoded['mode']), 'Missing required field: "mode"');
		}
		self::assertTrue(isset($decoded['timing']), 'Missing required field: "timing"');
		self::assertTrue(isset($decoded['scoring']), 'Missing required field: "scoring"');
		self::assertTrue(isset($decoded['players']), 'Missing required field: "players"');
		self::assertTrue(isset($decoded['teams']), 'Missing required field: "teams"');

		// Test if the data is the same
		self::assertSame($game->code, $decoded['code']);
		self::assertEquals(
			[
				'date'          => $game->start?->format('Y-m-d H:i:s.u'),
				'timezone_type' => 3,
				'timezone'      => $game->start?->getTimezone()->getName()
			],
			$decoded['start']
		);
		self::assertEquals(
			[
				'date'          => $game->end?->format('Y-m-d H:i:s.u'),
				'timezone_type' => 3,
				'timezone'      => $game->end?->getTimezone()->getName()
			],
			$decoded['end']
		);
		/* @phpstan-ignore-next-line */
		self::assertSame($game->mode?->id, $decoded['mode']['id']);
		/* @phpstan-ignore-next-line */
		self::assertSame($game->mode?->name, $decoded['mode']['name']);
		/* @phpstan-ignore-next-line */
		self::assertSame($game->mode?->type, GameModeType::tryFrom($decoded['mode']['type']));
		/* @phpstan-ignore-next-line */
		self::assertEquals(get_object_vars($game->timing), $decoded['timing']);
		/* @phpstan-ignore-next-line */
		self::assertEquals(get_object_vars($game->scoring), $decoded['scoring']);
		/* @phpstan-ignore-next-line */
		self::assertCount(count($game->getPlayers()), $decoded['players']);
		/* @phpstan-ignore-next-line */
		self::assertCount(count($game->getTeams()), $decoded['teams']);

		// Test players
		/**
		 * @var int $key
		 * @phpstan-ignore-next-line
		 */
		foreach ($decoded['players'] as $key => $playerData) {
			$player = $game->getPlayers()->get($key);
			// Test player's data
			foreach (['name', 'score', 'vest', 'shots', 'accuracy', 'hits', 'deaths', 'position'] as $field) {
				/* @phpstan-ignore-next-line */
				self::assertTrue(isset($playerData[$field]), 'Missing required field: "player.'.$field.'"');
				/* @phpstan-ignore-next-line */
				self::assertEquals($player->{$field}, $playerData[$field]);
			}
		}
		// Test teams
		/**
		 * @var int $key
		 * @phpstan-ignore-next-line
		 */
		foreach ($decoded['teams'] as $key => $teamData) {
			$team = $game->getTeams()->get($key);
			// Test team's data
			foreach (['name', 'score', 'color', 'position'] as $field) {
				/* @phpstan-ignore-next-line */
				self::assertTrue(isset($teamData[$field]), 'Missing required field: "team.'.$field.'"');
				/* @phpstan-ignore-next-line */
				self::assertEquals($team->{$field}, $teamData[$field]);
			}
		}
	}

	/**
	 * @param Game $game
	 *
	 * @dataProvider getRandomGames
	 *
	 * @return void
	 */
	public function testImportExportValidity(Game $game) : void {
		// Get game's JSON and then decode it back to Game object
		$json = json_encode($game, JSON_THROW_ON_ERROR);
		/** @var Game $class */
		$class = $game::class;
		/* @phpstan-ignore-next-line */
		$decoded = $class::fromJson(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

		// Test all fields
		foreach ($game::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $propertyReflection) {
			$field = $propertyReflection->getName();
			if (in_array($field, self::SKIP_FIELDS, true)) {
				continue;
			}
			if ($game->{$field} instanceof Model) {
				foreach ($game->{$field}::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $propertyReflection2) {
					$field2 = $propertyReflection2->getName();
					if (in_array($field, self::SKIP_FIELDS, true)) {
						continue;
					}
					self::assertEquals($game->{$field}->{$field2}, $decoded->{$field}->{$field2}, 'Failed asserting that the properties are the same: "game.'.$field.'.'.$field2.'" ('.json_encode($game->{$field}).')');
				}
			}
			else {
				self::assertEquals($game->{$field}, $decoded->{$field}, 'Failed asserting that the properties are the same: "game.'.$field.'"');
			}
		}

		// Test players
		foreach ($game->getPlayers() as $player) {
			/** @var Player $player */
			/** @var Player|null $decodedPlayer */
			$decodedPlayer = $decoded->getPlayers()->query()->filter('name', $player->name)->first();
			self::assertTrue(isset($decodedPlayer), 'Cannot find player "'.$player->name.'"');
			foreach ($player::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $propertyReflection) {
				$field = $propertyReflection->getName();
				if (in_array($field, self::SKIP_FIELDS, true)) {
					continue;
				}
				if ($player->{$field} instanceof Model) {
					foreach ($player->{$field}::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $propertyReflection2) {
						$field2 = $propertyReflection2->getName();
						if (in_array($field2, self::SKIP_FIELDS, true)) {
							continue;
						}
						self::assertEquals($player->{$field}->{$field2}, $decodedPlayer->{$field}->{$field2}, 'Failed asserting that the properties are the same: "player.'.$field.'.'.$field2.'" - '.$json);
					}
				}
				else {
					self::assertEquals($player->{$field}, $decodedPlayer->{$field}, 'Failed asserting that the properties are the same: "player.'.$field.'" - '.$json);
				}
			}
			// Test hits
			foreach ($player->getHitsPlayers() as $key => $hits) {
				/* @phpstan-ignore-next-line */
				self::assertEquals($hits->count, $decodedPlayer->getHitsPlayer($hits->playerTarget));
			}
			// Test team
			/* @phpstan-ignore-next-line */
			self::assertEquals($player->getTeamColor(), $decodedPlayer->getTeamColor(), 'Failed asserting that the team colors match.');
		}

		// Test teams
		foreach ($game->getTeams() as $team) {
			/** @var Team $team */
			/** @var Team|null $decodedPlayer */
			$decodedTeam = $decoded->getTeams()->query()->filter('name', $team->name)->first();
			self::assertTrue(isset($decodedTeam), 'Cannot find team "'.$team->name.'"');
			foreach ($team::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $propertyReflection) {
				$field = $propertyReflection->getName();
				if (in_array($field, self::SKIP_FIELDS, true)) {
					continue;
				}
				if ($team->{$field} instanceof Model) {
					foreach ($team->{$field}::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $propertyReflection2) {
						$field2 = $propertyReflection2->getName();
						if (in_array($field2, self::SKIP_FIELDS, true)) {
							continue;
						}
						self::assertEquals($team->{$field}->{$field2}, $decodedTeam->{$field}->{$field2}, 'Failed asserting that the properties are the same: "team.'.$field.'.'.$field2.'"');
					}
				}
				else {
					self::assertEquals($team->{$field}, $decodedTeam->{$field}, 'Failed asserting that the properties are the same: "team.'.$field.'"');
				}
			}
		}
	}

}