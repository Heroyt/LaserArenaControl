<?php

namespace App\Services;

use Lsr\Logging\Logger;
use App\Models\Tournament\League;
use App\Models\Tournament\Tournament;
use App\Models\Tournament\Team;
use App\Models\Tournament\Player;
use App\Models\Tournament\PlayerSkill;
use App\GameModels\Game\Enums\GameModeType;

class TournamentProvider
{

	private Logger $logger;

	public function __construct(
		private readonly LigaApi $api,
		private readonly PlayerProvider $playerProvider,
	) {
		$this->logger = new Logger(LOG_DIR.'services/', 'tournaments');
	}

	public function sync() : bool {
		// Sync leagues
		/** @var array{id:int,name:string,image:string|null,description:string|null}[] $leagues */
		$response = $this->api->get('/api/league');
		$leagues = json_decode($response->getBody(), true);
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
			$this->logger->debug('Saving league - '.json_encode($leagueLocal));
		}

		// Sync tournaments
		/** @var array{id:int,name:string,image:string|null,description:string|null,format:string,teamSize:int,subSize:int,active:bool,start:array{date:string,timezone:string},end:null|array{date:string,timezone:string}}[] $tournaments */
		$response = $this->api->get('/api/tournament');
		$tournaments = json_decode($response->getBody(), true);
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
			$tournamentLocal->start = new \DateTimeImmutable($tournament['start']['date']);
			$tournamentLocal->end = isset($tournament['end']) ? new \DateTimeImmutable($tournament['end']['date']) : null;
			if (isset($tournament['league']['id'])) {
				$tournamentLocal->league = League::getByPublicId($tournament['league']['id']);
			}
			$tournamentLocal->save();

			/** @var array{id:int,name:string,image:string|null,players:array{id:int,nickname:string,name:string|null,surname:string|null,captain:bool,sub:bool,email:string|null,phone:string|null,skill:string,birthYear:int|null,image:string|null,user:null|array{id:int,nickname:string,code:string,email:string}}[]}[] $teams */
			$response = $this->api->get('/api/tournament/'.$tournamentLocal->idPublic.'/teams', ['withPlayers' => '1']);
			$teams = json_decode($response->getBody(), true);
			foreach($teams as $team) {
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
						$this->logger->debug('Player #'.$player['id'].' user - '.json_encode($player['user']));
						$playerLocal->user = $this->playerProvider->getPlayerObjectFromData($player['user']);
					}

					$playerLocal->save();
				}
			}
		}

		return true;
	}

}