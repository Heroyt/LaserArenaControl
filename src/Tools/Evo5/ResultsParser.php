<?php

namespace App\Tools\Evo5;

use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\ResultsParseException;
use App\Models\Factory\GameModeFactory;
use App\Models\Game\Evo5\Game;
use App\Models\Game\Evo5\Player;
use App\Models\Game\Evo5\Team;
use App\Models\Game\Scoring;
use App\Models\Game\Timing;
use App\Tools\AbstractResultsParser;
use DateTime;

class ResultsParser extends AbstractResultsParser
{

	public const EMPTY_DATE = '20000101000000';

	/**
	 * @return Game
	 * @throws ResultsParseException
	 * @throws GameModeNotFoundException
	 */
	public function parse() : Game {
		$game = new Game();

		$pathInfo = pathinfo($this->fileName);
		preg_match('/(\d+)/', $pathInfo['filename'], $matches);
		$game->fileNumber = $matches[0] ?? 0;

		preg_match_all('/([A-Z]+){([^{}]*)}#/', $this->fileContents, $matches);

		[$lines, $titles, $argsAll] = $matches;

		if (empty($titles) || empty($argsAll)) {
			throw new ResultsParseException('The results file cannot be parsed: '.$this->fileName);
		}

		$keysVests = [];
		$currKey = 1;
		foreach ($titles as $key => $title) {
			$args = $this->getArgs($argsAll[$key]);
			switch ($title) {
				case 'SITE':
					if ($args[2] !== 'EVO-5 MAXX') {
						throw new ResultsParseException('Invalid results system type. - '.$title.': '.json_encode($args, JSON_THROW_ON_ERROR));
					}
					break;
				case 'GAME':
					[$gameNumber, $a, $dateStart, $dateEnd, $playerCount] = $args;
					$game->gameNumber = (int) $gameNumber;
					$game->playerCount = (int) $playerCount;
					if ($dateStart !== $this::EMPTY_DATE) {
						$game->start = DateTime::createFromFormat('YmdHis', $dateStart);
						$game->started = true;
					}
					if ($dateEnd !== $this::EMPTY_DATE) {
						$game->end = DateTime::createFromFormat('YmdHis', $dateEnd);
						$game->finished = true;
					}
					break;
				case 'TIMING':
					$game->timing = new Timing(before: $args[0], gameLength: $args[1], after: $args[2]);
					break;
				case 'STYLE':
					$game->modeName = $args[0];
					$game->mode = GameModeFactory::find($args[0], (int) $args[2], 'Evo5');
					break;
				case 'SCORING':
					$game->scoring = new Scoring(...$args);
					break;
				case 'GROUP':
					// TODO: Maybe parse additional info
					break;
				case 'PACK':
					$player = new Player();
					$game->getPlayers()->set($player, $args[0]);
					$player->setGame($game);
					$player->vest = $args[0];
					$keysVests[$player->vest] = $currKey++;
					$player->name = $args[1];
					$player->teamNum = $args[2];
					break;
				case 'TEAM':
					$team = new Team();
					$game->getTeams()->set($team, $args[0]);
					$team->setGame($game);
					if (!isset($player)) {
						throw new ResultsParseException('Cannot find Team - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					$team->name = $args[1];
					$team->color = $args[0];
					$team->playerCount = $args[2];
					$players = $game->getPlayers()->query()->filter('teamNum', $team->color)->get();
					$team->addPlayer(...$players);
					foreach ($players as $player) {
						$player->setTeam($team);
					}
					break;
				case 'PACKX':
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
				case 'PACKY':
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
				case 'TEAMX':
					/** @var Team $team */
					$team = $game->getTeams()->get($args[0]);
					if (!isset($player)) {
						throw new ResultsParseException('Cannot find Team - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					$team->score = $args[1];
					$team->position = $args[2];
					break;
				case 'HITS':
					/** @var Player $player */
					$player = $game->getPlayers()->get($args[0]);
					if (!isset($player)) {
						throw new ResultsParseException('Cannot find Player - '.json_encode($args[0], JSON_THROW_ON_ERROR).PHP_EOL.$this->fileName.':'.PHP_EOL.$this->fileContents);
					}
					foreach ($game->getPlayers() as $player2) {
						$player->addHits($player2, $args[$keysVests[$player2->vest] ?? -1] ?? 0);
					}
					break;
			}
		}

		return $game;
	}

	private function getArgs(string $args) : array {
		return array_map('trim', explode(',', $args));
	}

}