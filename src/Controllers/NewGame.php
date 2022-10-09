<?php

namespace App\Controllers;

use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\GameModels\Vest;
use App\Models\MusicMode;
use JsonException;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

class NewGame extends Controller
{

	protected string $title       = 'New game';
	protected string $description = '';

	/**
	 * @return void
	 * @throws GameModeNotFoundException
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 */
	public function show() : void {
		$this->params['loadGame'] = !empty($_GET['game']) ? GameFactory::getByCode($_GET['game']) : null;
		$this->params['system'] = $_GET['system'] ?? first(GameFactory::getSupportedSystems());
		$this->params['vests'] = Vest::getForSystem($this->params['system']);
		$this->params['colors'] = GameFactory::getAllTeamsColors()[$this->params['system']];
		$this->params['teamNames'] = GameFactory::getAllTeamsNames()[$this->params['system']];
		$this->params['gameModes'] = GameModeFactory::getAll(['system' => $this->params['system']]);
		$this->params['musicModes'] = MusicMode::getAll();
		$this->view('pages/new-game/index');
	}

	/**
	 * Create a new game
	 *
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws TemplateDoesNotExistException
	 */
	#[Post('/')]
	public function process(Request $request) : never {
		Timer::start('newGame.process');
		$data = [
			'meta'    => [
				'music' => empty($request->post['music']) ? null : (int) $request->post['music'],
			],
			'players' => [],
			'teams'   => [],
		];

		Timer::start('newGame.mode');
		try {
			$mode = GameModeFactory::getById($request->post['game-mode'] ?? 0);
		} catch (GameModeNotFoundException $e) {
		}

		if (isset($mode)) {
			$data['meta']['mode'] = $mode->loadName;
			if (!empty($request->post['variation'])) {
				$data['meta']['variations'] = [];
				foreach ($request->post['variation'] as $id => $suffix) {
					$data['meta']['variations'][$id] = $suffix;
					$data['meta']['mode'] .= $suffix;
				}
			}
		}
		Timer::start('newGame.mode');

		$teams = [];

		// Validate and parse players
		Timer::start('newGame.players');
		foreach ($request->post['player'] as $vest => $player) {
			if (empty(trim($player['name']))) {
				continue;
			}
			if (!isset($player['team']) || $player['team'] === '') {
				if (!isset($mode) || $mode->isTeam()) {
					continue;
				}
				$player['team'] = '2';
			}
			$asciiName = substr(Strings::toAscii($player['name']), 0, 12);
			if ($player['name'] !== $asciiName) {
				$data['meta']['p'.$vest.'n'] = $player['name'];
			}
			$data['players'][] = [
				'vest' => $vest,
				'name' => $asciiName,
				'team' => $player['team'],
				'vip'  => ((int) $player['vip']) === 1,
			];
			if (!isset($teams[(string) $player['team']])) {
				$teams[(string) $player['team']] = 0;
			}
			$teams[(string) $player['team']]++;
		}
		Timer::stop('newGame.players');

		Timer::start('newGame.teams');
		foreach ($request->post['team'] as $key => $team) {
			$asciiName = Strings::toAscii($team['name']);
			if ($team['name'] !== $asciiName) {
				$data['meta']['t'.$key.'n'] = $team['name'];
			}
			$data['teams'][] = [
				'key'         => $key,
				'name'        => $asciiName,
				'playerCount' => $teams[(string) $key] ?? 0,
			];
		}
		Timer::stop('newGame.teams');

		Timer::start('newGame.modify');
		if (isset($mode) && $mode instanceof CustomLoadMode) {
			$data = $mode->modifyGameDataBeforeLoad($data);
		}
		Timer::stop('newGame.modify');

		Timer::start('newGame.finish');
		$data['teams'] = array_filter($data['teams'], static fn($team) => $team['playerCount'] > 0);
		$data['meta']['hash'] = md5(json_encode($data['players'], JSON_THROW_ON_ERROR));
		Timer::stop('newGame.finish');

		// Render the game info into a load file
		Timer::start('newGame.render');
		$content = $this->latte->viewToString('gameFiles/evo5', $data);
		$loadDir = LMX_DIR.Info::get('evo5_load_file', 'games/');
		if (file_exists($loadDir) && is_dir($loadDir)) {
			file_put_contents($loadDir.'0000.game', $content);
		}
		Timer::stop('newGame.render');


		// Set up a correct music file
		Timer::start('newGame.music');
		if (isset($data['meta']['music'])) {
			try {
				$music = MusicMode::get($data['meta']['music']);
				copy($music->fileName, LMX_DIR.'music/evo5.mp3');
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
				// Not critical, doesn't need to do anything
			}
		}
		Timer::start('newGame.music');
		Timer::stop('newGame.process');
		$this->respond(['status' => 'ok', 'mode' => $data['meta']['mode']]);
	}

}