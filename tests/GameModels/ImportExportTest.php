<?php

namespace GameModels;

use App\Core\AbstractModel;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class ImportExportTest extends TestCase
{

	/**
	 * Get 20 random games
	 *
	 * @return Game[][]
	 */
	public function getRandomGames() : array {
		$data = [];
		$gameRows = GameFactory::queryGames(true)->orderBy('RAND()')->limit(20)->fetchAll();
		foreach ($gameRows as $row) {
			$data[] = [GameFactory::getByCode($row->code)];
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
		$decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

		// Test if the data contains all necessary information
		self::assertNotEmpty($decoded);
		self::assertTrue(isset($decoded['code']), 'Missing required field: "code"');
		self::assertTrue(isset($decoded['start']), 'Missing required field: "start"');
		self::assertTrue(isset($decoded['end']), 'Missing required field: "end"');
		self::assertTrue(isset($decoded['mode']), 'Missing required field: "mode"');
		self::assertTrue(isset($decoded['timing']), 'Missing required field: "timing"');
		self::assertTrue(isset($decoded['scoring']), 'Missing required field: "scoring"');
		self::assertTrue(isset($decoded['players']), 'Missing required field: "players"');
		self::assertTrue(isset($decoded['teams']), 'Missing required field: "teams"');

		// Test if the data is the same
		self::assertSame($game->code, $decoded['code']);
		self::assertEquals(
			[
				'date'          => $game->start->format('Y-m-d H:i:s.u'),
				'timezone_type' => 3,
				'timezone'      => $game->start->getTimezone()->getName()
			],
			$decoded['start']
		);
		self::assertEquals(
			[
				'date'          => $game->end->format('Y-m-d H:i:s.u'),
				'timezone_type' => 3,
				'timezone'      => $game->end->getTimezone()->getName()
			],
			$decoded['end']
		);
		self::assertSame($game->mode->id, $decoded['mode']['id']);
		self::assertSame($game->mode->name, $decoded['mode']['name']);
		self::assertSame($game->mode->type, GameModeType::tryFrom($decoded['mode']['type']));
		self::assertEquals(get_object_vars($game->timing), $decoded['timing']);
		self::assertEquals(get_object_vars($game->scoring), $decoded['scoring']);
		self::assertCount(count($game->getPlayers()), $decoded['players']);
		self::assertCount(count($game->getTeams()), $decoded['teams']);

		// Test players
		foreach ($decoded['players'] as $key => $playerData) {
			$player = $game->getPlayers()->get($key);
			// Test player's data
			foreach (['name', 'score', 'vest', 'shots', 'accuracy', 'hits', 'deaths', 'position'] as $field) {
				self::assertTrue(isset($playerData[$field]), 'Missing required field: "player.'.$field.'"');
				self::assertEquals($player->{$field}, $playerData[$field]);
			}
		}
		// Test teams
		foreach ($decoded['teams'] as $key => $teamData) {
			$team = $game->getTeams()->get($key);
			// Test team's data
			foreach (['name', 'score', 'color', 'position'] as $field) {
				self::assertTrue(isset($teamData[$field]), 'Missing required field: "team.'.$field.'"');
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
		$decoded = $class::fromJson(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

		// Test all fields
		foreach ($game::DEFINITION as $field => $info) {
			if (isset($info['noTest']) && $info['noTest']) {
				continue;
			}
			if ($game->{$field} instanceof AbstractModel) {
				foreach ($game->{$field}::DEFINITION as $field2 => $info2) {
					if (isset($info2['noTest']) && $info2['noTest']) {
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
			foreach ($player::DEFINITION as $field => $info) {
				if (isset($info['noTest']) && $info['noTest']) {
					continue;
				}
				if ($player->{$field} instanceof AbstractModel) {
					foreach ($player->{$field}::DEFINITION as $field2 => $info2) {
						if (isset($info2['noTest']) && $info2['noTest']) {
							continue;
						}
						self::assertEquals($player->{$field}->{$field2}, $decodedPlayer->{$field}->{$field2}, 'Failed asserting that the properties are the same: "player.'.$field.'.'.$field2.'"');
					}
				}
				else {
					self::assertEquals($player->{$field}, $decodedPlayer->{$field}, 'Failed asserting that the properties are the same: "player.'.$field.'"');
				}
			}
			// Test hits
			foreach ($player->getHitsPlayers() as $key => $hits) {
				self::assertEquals($hits->count, $decodedPlayer->getHitsPlayer($hits->playerTarget));
			}
			// Test team
			self::assertEquals($player->getTeamColor(), $decodedPlayer->getTeamColor());
		}

		// Test teams
		foreach ($game->getTeams() as $team) {
			/** @var Team $team */
			/** @var Team|null $decodedPlayer */
			$decodedTeam = $decoded->getTeams()->query()->filter('name', $team->name)->first();
			self::assertTrue(isset($decodedTeam), 'Cannot find team "'.$team->name.'"');
			foreach ($team::DEFINITION as $field => $info) {
				if (isset($info['noTest']) && $info['noTest']) {
					continue;
				}
				if ($team->{$field} instanceof AbstractModel) {
					foreach ($team->{$field}::DEFINITION as $field2 => $info2) {
						if (isset($info2['noTest']) && $info2['noTest']) {
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