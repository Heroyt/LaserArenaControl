<?php

namespace App\Services;

use App\GameModels\Game\Enums\GameModeType;
use App\Models\Tournament\League;
use App\Models\Tournament\Player;
use App\Models\Tournament\PlayerSkill;
use App\Models\Tournament\Team;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentPresetType;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Logging\Logger;
use TournamentGenerator\Tournament as TournamentGenerator;

class TournamentProvider
{

	private Logger $logger;

	public function __construct(
		private readonly LigaApi        $api,
		private readonly PlayerProvider $playerProvider,
	) {
		$this->logger = new Logger(LOG_DIR . 'services/', 'tournaments');
	}

	/**
	 * @return bool
	 */
	public function sync(): bool {
		// Sync leagues
		try {
			$response = $this->api->get('/api/league');
			/** @var array{id:int,name:string,image:string|null,description:string|null}[] $leagues */
			$leagues = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
			$this->logger->debug('Got ' . count($leagues) . ' leagues');
			foreach ($leagues as $league) {
				$leagueLocal = League::getByPublicId($league['id']);
				if (!isset($leagueLocal)) {
					$leagueLocal = new League();
				}
				$leagueLocal->idPublic = $league['id'];
				$leagueLocal->name = $league['name'];
				$leagueLocal->description = $league['description'];
				$leagueLocal->image = $league['image'];
				$leagueLocal->save();
				try {
					$this->logger->debug('Saving league - ' . json_encode($leagueLocal, JSON_THROW_ON_ERROR));
				} catch (JsonException $e) {
					$this->logger->exception($e);
				}
			}

			// Sync tournaments
			$response = $this->api->get('/api/tournament');
			/** @var array{id:int,name:string,image:string|null,description:string|null,league:null|array{id:int,name:string},format:string,teamSize:int,subCount:int,active:bool,start:array{date:string,timezone:string},end:null|array{date:string,timezone:string}}[] $tournaments */
			$tournaments = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
			foreach ($tournaments as $tournament) {
				$tournamentLocal = Tournament::getByPublicId($tournament['id']);
				if (!isset($tournamentLocal)) {
					$tournamentLocal = new Tournament();
				}
				$tournamentLocal->idPublic = $tournament['id'];
				$tournamentLocal->name = $tournament['name'];
				$tournamentLocal->description = $tournament['description'];
				$tournamentLocal->image = $tournament['image'];
				$tournamentLocal->format = GameModeType::from($tournament['format']);
				$tournamentLocal->teamSize = $tournament['teamSize'];
				$tournamentLocal->subCount = $tournament['subCount'];
				$tournamentLocal->active = $tournament['active'];
				$tournamentLocal->start = new DateTimeImmutable($tournament['start']['date']);
				$tournamentLocal->end = isset($tournament['end']) ? new DateTimeImmutable($tournament['end']['date']) : null;
				if (isset($tournament['league']['id'])) {
					$tournamentLocal->league = League::getByPublicId($tournament['league']['id']);
				}
				$tournamentLocal->save();

				$response = $this->api->get('/api/tournament/' . $tournamentLocal->idPublic . '/teams', ['withPlayers' => '1']);
				/** @var array{id:int,name:string,image:string|null,players:array{id:int,nickname:string,name:string|null,surname:string|null,captain:bool,sub:bool,email:string|null,phone:string|null,skill:string,birthYear:int|null,image:string|null,user:null|array{id:int,nickname:string,code:string,email:string}}[]}[] $teams */
				$teams = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
				foreach ($teams as $team) {
					$teamLocal = Team::getByPublicId($team['id']);
					if (!isset($teamLocal)) {
						$teamLocal = new Team();
					}
					$teamLocal->idPublic = $team['id'];
					$teamLocal->tournament = $tournamentLocal;
					$teamLocal->name = $team['name'];
					$teamLocal->image = $team['image'];

					$teamLocal->save();

					/** @var array{id:int,nickname:string,name:string|null,surname:string|null,captain:bool,sub:bool,email:string|null,phone:string|null,skill:string,birthYear:int|null,image:string|null,user:null|array{id:int,nickname:string,code:string,arena:int,email:string,stats:array{rank:int,gamesPlayed:int,arenasPlayed:int},connections:array{type:string,identifier:string}[]}} $player */
					foreach ($team['players'] as $player) {
						$playerLocal = Player::getByPublicId($player['id']);
						if (!isset($playerLocal)) {
							$playerLocal = new Player();
						}
						$playerLocal->idPublic = $player['id'];
						$playerLocal->tournament = $tournamentLocal;
						$playerLocal->team = $teamLocal;
						$playerLocal->nickname = $player['nickname'];
						$playerLocal->name = $player['name'];
						$playerLocal->surname = $player['surname'];
						$playerLocal->email = $player['email'];
						$playerLocal->phone = $player['phone'];
						$playerLocal->birthYear = $player['birthYear'];
						$playerLocal->image = $player['image'];
						$playerLocal->skill = PlayerSkill::from($player['skill']);
						$playerLocal->captain = $player['captain'];
						$playerLocal->sub = $player['sub'];

						if (isset($player['user'])) {
							$this->logger->debug('Player #' . $player['id'] . ' user - ' . json_encode($player['user'], JSON_THROW_ON_ERROR));
							$playerLocal->user = $this->playerProvider->getPlayerObjectFromData($player['user']);
						}

						$playerLocal->save();
					}
				}
			}
		} catch (Exception|GuzzleException $e) {
			// @phpstan-ignore-next-line
			$this->logger->exception($e);
			return false;
		}

		return true;
	}

	public function reset(Tournament $tournament): void {
		$tournament->clearProgressions();
		$tournament->clearGroups();
		$tournament->clearGames();
	}

	/**
	 * @param TournamentPresetType $type
	 * @param Tournament $tournament
	 * @param int $gameLength
	 * @param int $gamePause
	 * @return TournamentGenerator
	 * @throws ValidationException
	 */
	public function createTournamentFromPreset(TournamentPresetType $type, Tournament $tournament, int $gameLength = 15, int $gamePause = 5): TournamentGenerator {
		$tournamentRozlos = new TournamentGenerator();
		foreach ($tournament->getTeams() as $team) {
			$tournamentRozlos->team($team->name, $team->id);
		}

		$tournamentRozlos->setPlay($gameLength)->setGameWait($gamePause);

		switch ($type) {
			case TournamentPresetType::ROUND_ROBIN:
				$tournamentRozlos->round()->group('A');
				$tournamentRozlos->splitTeams();
				break;
			case TournamentPresetType::TWO_GROUPS_ROBIN:
				$half = (int)floor(count($tournament->getTeams()) / 4);
				$round1 = $tournamentRozlos->round(lang('Kvalifikace'));
				$round2 = $tournamentRozlos->round(lang('Finále'));
				$groupA = $round1->group('A');
				$groupB = $round1->group('B');
				$groupC = $round2->group('C');
				$groupD = $round2->group('D');
				$groupA->progression($groupC, 0, $half);
				$groupA->progression($groupD, $half);
				$groupB->progression($groupC, 0, $half);
				$groupB->progression($groupD, $half);
				$tournamentRozlos->splitTeams($round1);
				break;
		}

		$tournamentRozlos->genGamesSimulate();

		return $tournamentRozlos;
	}

}