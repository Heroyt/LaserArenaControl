<?php

namespace App\Tools\Evo5;

use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ResultsParseException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\Evo5\Game;
use App\GameModels\Game\Evo5\Player;
use App\GameModels\Game\Evo5\Team;
use App\GameModels\Game\Scoring;
use App\GameModels\Game\Timing;
use App\Tools\AbstractResultsParser;
use DateTime;

class ResultsParser extends AbstractResultsParser
{

	/** @var string Default LMX date string passed when no distinct date should be used (= null) */
	public const EMPTY_DATE = '20000101000000';

	/**
	 * Parse a game results file and return a parsed object
	 *
	 * @return Game
	 * @throws ResultsParseException
	 * @throws GameModeNotFoundException
	 */
	public function parse() : Game {
		$game = new Game();

		// Results file info
		$pathInfo = pathinfo($this->fileName);
		preg_match('/(\d+)/', $pathInfo['filename'], $matches);
		$game->fileNumber = $matches[0] ?? 0;
		$fTime = filemtime($this->fileName);
		if (is_int($fTime)) {
			$game->fileTime = new DateTime();
			$game->fileTime->setTimestamp($fTime);
		}

		// Parse file into lines and arguments
		preg_match_all('/([A-Z]+){([^{}]*)}#/', $this->fileContents, $matches);
		[$lines, $titles, $argsAll] = $matches;

		// Check if parsing is successful and lines were found
		if (empty($titles) || empty($argsAll)) {
			throw new ResultsParseException('The results file cannot be parsed: '.$this->fileName);
		}

		$keysVests = [];
		$currKey = 1;
		foreach ($titles as $key => $title) {
			$args = $this->getArgs($argsAll[$key]);

			// To prevent calling the count() function multiple times - save the value
			$argsCount = count($args);

			switch ($title) {
				// SITE line contains information about the LMX arena and possibly version?
				// This can only be useful to validate if the results are from the correct system (EVO-5)
				case 'SITE':
					if ($args[2] !== 'EVO-5 MAXX') {
						throw new ResultsParseException('Invalid results system type. - '.$title.': '.json_encode($args, JSON_THROW_ON_ERROR));
					}
					break;

				// GAME contains general game information
				// - game number
				// - ???
				// - Start datetime (when the "Start game" button was pressed)
				// - Finish datetime (when the results are downloaded)
				// - Player count
				case 'GAME':
					if ($argsCount !== 5) {
						throw new ResultsParseException('Invalid argument count in GAME');
					}
					[$gameNumber, $a, $dateStart, $dateEnd, $playerCount] = $args;
					$game->gameNumber = (int) $gameNumber;
					$game->playerCount = (int) $playerCount;
					if ($dateStart !== $this::EMPTY_DATE) {
						$game->start = DateTime::createFromFormat('YmdHis', $dateStart);
					}
					if ($dateEnd !== $this::EMPTY_DATE) {
						$game->end = DateTime::createFromFormat('YmdHis', $dateEnd);
					}
					break;

				// TIMING contains all game settings regarding game times
				// - Start time [s]
				// - Play time [min]
				// - End time [s]
				// - Play start time [datetime]
				// - Play end time [datetime]
				// - End time [datetime] (Real end - after the play ended and after end time)
				case 'TIMING':
					if ($argsCount !== 6 && $argsCount !== 5) {
						throw new ResultsParseException('Invalid argument count in TIMING');
					}
					$game->timing = new Timing(before: $args[0], gameLength: $args[1], after: $args[2]);
					$dateStart = $args[3];
					$now = new DateTime();
					if ($dateStart !== $this::EMPTY_DATE) {
						$game->start = DateTime::createFromFormat('YmdHis', $dateStart);
						$game->started = $now > $game->start;
					}
					$dateEnd = $args[3];
					$now = new DateTime();
					if ($dateEnd !== $this::EMPTY_DATE) {
						$game->end = DateTime::createFromFormat('YmdHis', $dateEnd);
						$game->finished = $now->getTimestamp() > ($game->end->getTimestamp() + $game->timing->after);
					}
					break;

				// STYLE contains game mode information
				// - Game mode's name
				// - Game mode's description
				// - Team (1) / Solo (0) game type
				// - Play length [min]
				// - ??
				case 'STYLE':
					if ($argsCount !== 5 && $argsCount !== 4) {
						throw new ResultsParseException('Invalid argument count in STYLE');
					}
					$game->modeName = $args[0];
					$game->mode = GameModeFactory::find($args[0], ((int) $args[2]) === 1 ? GameModeType::TEAM : GameModeType::SOLO, 'Evo5');
					break;

				// STYLEX contains additional game mode settings
				// - Respawn time [s]
				// - Starting ammo
				// - Starting lives
				// - High-score
				// - ???
				case 'STYLEX':
					if ($argsCount < 3) {
						throw new ResultsParseException('Invalid argument count in STYLE');
					}
					$game->respawn = (int) $args[0];
					$game->ammo = (int) $args[1];
					$game->lives = (int) $args[2];
					break;

				// STYLELEDS contains lightning settings
				// - 11 unknown arguments
				case 'STYLELEDS':
					// STYLEFLAGS
					// - 27 Unknown arguments
				case 'STYLEFLAGS':
					// STYLESOUNDS
					// - ???
				case 'STYLESOUNDS':
					break;

				// SCORING contains score settings
				// - Death enemy
				// - Hit enemy
				// - Death teammate
				// - Hit teammate
				// - Death from pod
				// - Score per shot
				// - Score per machine gun
				// - Score per invisibility
				// - Score per agent
				// - Score per shield
				case 'SCORING':
					if ($argsCount !== 16 && $argsCount !== 14) {
						throw new ResultsParseException('Invalid argument count in SCORING');
					}
					$game->scoring = new Scoring(...$args);
					break;

				// ENVIRONMENT contains sound and effects settings
				// - 5 unknown arguments
				// - Play music file
				// - 5 unknown arguments
				case 'ENVIRONMENT':
					// VIPSTYLE contains special mode settings
					// - 7 unknown arguments
				case 'VIPSTYLE':
					// VAMPIRESTYLE contains special mode settings
					// - 6 unknown arguments
				case 'VAMPIRESTYLE':
					// SWITCHSTYLE contains special mode settings
					// - 2 unknown arguments
				case 'SWITCHSTYLE':
					// ASSISTEDSTYLE contains special mode settings
					// - 8 unknown arguments
				case 'ASSISTEDSTYLE':
					// HITSTREAKSTYLE contains special mode settings
					// - 3 unknown arguments
				case 'HITSTREAKSTYLE':
					// SHOWDOWNSTYLE contains special mode settings
					// - 5 unknown arguments
				case 'SHOWDOWNSTYLE':
					// ACTIVITYSTYLE contains special mode settings
					// - 3 unknown arguments
				case 'ACTIVITYSTYLE':
					// TERMINATESTYLE contains special mode settings
					// - 1 unknown argument
				case 'TERMINATESTYLE':
					// MINESTYLE contains pods settings
					// - Pod number
					// - 3 unknown arguments
					// - Pod name
				case 'MINESTYLE':
					// GROUP contains additional game notes
					// - 2 arguments
				case 'GROUP':
					// TODO: Maybe parse additional info
					break;

				// PACK contains information about vest settings
				// - Vest number
				// - Player name
				// - Team number
				// - ???
				// - VIP
				// - 2 unknown arguments
				case 'PACK':
					if ($argsCount !== 4 && $argsCount !== 7) {
						throw new ResultsParseException('Invalid argument count in PACK');
					}
					$player = new Player();
					$game->getPlayers()->set($player, $args[0]);
					$player->setGame($game);
					$player->vest = $args[0];
					$keysVests[$player->vest] = $currKey++;
					$player->name = $args[1];
					$player->teamNum = $args[2];
					break;

				// TEAM contains team info
				// - Team number
				// - Team name
				// - Player count
				case 'TEAM':
					if ($argsCount !== 3) {
						throw new ResultsParseException('Invalid argument count in TEAM');
					}
					$team = new Team();
					$game->getTeams()->set($team, $args[0]);
					$team->setGame($game);
					$team->name = $args[1];
					$team->color = $args[0];
					$team->playerCount = $args[2];
					break;

				// PACKX contains player's results
				// - Vest number
				// - Score
				// - Shots
				// - Hits
				// - Deaths
				// - Position
				// - ???
				// - ???
				case 'PACKX':
					if ($argsCount !== 7 && $argsCount !== 8) {
						throw new ResultsParseException('Invalid argument count in PACKX');
					}
					/** @var Player $player */
					$player = $game->getPlayers()->get($args[0]);
					if (!isset($player)) {
						throw new ResultsParseException('Cannot find Player - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					$player->score = $args[1];
					$player->shots = $args[2];
					$player->hits = $args[3];
					$player->deaths = $args[4];
					$player->position = $args[5];
					break;

				// PACKY contains player's additional results
				// - Vest number
				// - Score for shots
				// - Score for bonuses
				// - Score for pod deaths
				// - Ammo remaining
				// - Accuracy
				// - Pod hits
				// - Agent
				// - Invisibility
				// - Machine gun
				// - Shield
				// - Enemy hits
				// - Teammate hits
				// - Enemy deaths
				// - Teammate deaths
				// - 7 unknown arguments
				case 'PACKY':
					if ($argsCount !== 16 && $argsCount !== 22 && $argsCount !== 23) {
						throw new ResultsParseException('Invalid argument count in PACKY');
					}
					/** @var Player $player */
					$player = $game->getPlayers()->get($args[0]);
					if (!isset($player)) {
						throw new ResultsParseException('Cannot find Player - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					$player->shotPoints = $args[1] ?? 0;
					$player->scoreBonus = $args[2] ?? 0;
					$player->scorePowers = $args[3] ?? 0;
					$player->scoreMines = $args[4] ?? 0;
					$player->ammoRest = $args[5] ?? 0;
					$player->accuracy = $args[6] ?? 0;
					$player->minesHits = $args[7] ?? 0;
					$player->bonus->agent = $args[8] ?? 0;
					$player->bonus->invisibility = $args[9] ?? 0;
					$player->bonus->machineGun = $args[10] ?? 0;
					$player->bonus->shield = $args[11] ?? 0;
					$player->hitsOther = $args[12] ?? 0;
					$player->hitsOwn = $args[13] ?? 0;
					$player->deathsOther = $args[14] ?? 0;
					$player->deathsOwn = $args[15] ?? 0;
					break;

				// TEAMX contains information about team's score
				// - Team number
				// - Score
				// - Position
				case 'TEAMX':
					if ($argsCount !== 3) {
						throw new ResultsParseException('Invalid argument count in TEAMX');
					}
					/** @var Team $team */
					$team = $game->getTeams()->get($args[0]);
					if (!isset($team)) {
						throw new ResultsParseException('Cannot find Team - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					$team->score = $args[1];
					$team->position = $args[2];
					break;

				// HITS contain information about individual hits between players
				// - Vest number
				// - X (X > 0) values for each player indicating how many times did a player with "Vest number" hit that player
				case 'HITS':
					if ($argsCount < 2) {
						throw new ResultsParseException('Invalid argument count in HITS');
					}
					/** @var Player $player */
					$player = $game->getPlayers()->get($args[0]);
					if (!isset($player)) {
						throw new ResultsParseException('Cannot find Player - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					foreach ($game->getPlayers() as $player2) {
						$player->addHits($player2, $args[$keysVests[$player2->vest] ?? -1] ?? 0);
					}
					break;

				// GAMECLONES contain information about cloned games
				case 'GAMECLONES':
					// TODO: Detect clones and deal with them
					break;
			}

			// TODO: Figure out the unknown arguments

			// Set player teams
			foreach ($game->getPlayers() as $player) {
				// Find team
				foreach ($game->getTeams() as $team) {
					if ($player->teamNum === $team->color) {
						$player->setTeam($team);
						break;
					}
				}
			}
		}

		return $game;
	}

	/**
	 * Get arguments from a line
	 *
	 * Arguments are separated by a comma ',' character.
	 *
	 * @param string $args Concatenated arguments
	 *
	 * @return string[] Separated and trimmed arguments, not type-casted
	 */
	private function getArgs(string $args) : array {
		return array_map('trim', explode(',', $args));
	}

}