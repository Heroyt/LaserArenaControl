<?php

namespace App\Controllers;

use App\Core\Info;
use App\DataObjects\NewGame\HookedTemplates;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\GameModels\Vest;
use App\Models\GameGroup;
use App\Models\MusicMode;
use App\Services\FeatureConfig;
use JsonException;
use LAC\Modules\Core\ControllerDecoratorInterface;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Helpers\Tools\Timer;
use Lsr\Interfaces\RequestInterface;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;

class NewGame extends Controller
{

	public HookedTemplates $hookedTemplates;
	protected string $title       = 'New game';
	protected string $description = '';
	/** @var ControllerDecoratorInterface[] */
	private array $decorators = [];

	public function __construct(
		Latte                          $latte,
		private readonly FeatureConfig $featureConfig
	) {
		parent::__construct($latte);
	}

	public function init(RequestInterface $request): void {
		$this->params['addCss'] = [];
		$this->params['addJs'] = [];
		parent::init($request);
		/** @var array<string, mixed> $decorators */
		$decorators = App::getContainer()->findByTag('newGameDecorator');
		bdump($decorators);
		foreach ($decorators as $name => $attributes) {
			/** @var ControllerDecoratorInterface $decorator */
			$this->decorators[] = $decorator = App::getService($name);
			$decorator->setController($this)->init();
		}
	}

	/**
	 * @return void
	 * @throws GameModeNotFoundException
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 */
	public function show(): void {
		$this->hookedTemplates = new HookedTemplates();
		$this->params['addedTemplates'] = $this->hookedTemplates;
		$this->params['featureConfig'] = $this->featureConfig;
		$this->params['addCss'] = ['pages/newGame.css'];
		$this->params['loadGame'] = !empty($_GET['game']) ? GameFactory::getByCode($_GET['game']) : null;
		$this->params['system'] = $_GET['system'] ?? first(GameFactory::getSupportedSystems());
		$this->params['vests'] = Vest::getForSystem($this->params['system']);
		$this->params['colors'] = GameFactory::getAllTeamsColors()[$this->params['system']];
		$this->params['teamNames'] = GameFactory::getAllTeamsNames()[$this->params['system']];
		$this->params['gameModes'] = GameModeFactory::getAll(['system' => $this->params['system']]);
		$this->params['musicModes'] = MusicMode::getAll();
		$this->params['groups'] = GameGroup::getActive();
		foreach ($this->decorators as $decorator) {
			if ($decorator->decorates('show') && method_exists($decorator, 'decorateShow')) {
				$decorator->decorateShow();
			}
		}
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
	public function process(Request $request): never {
		Timer::start('newGame.process');
		/** @var array{
		 *   meta:array<string,string|numeric>,
		 *   players:array{vest:int,name:string,vip:bool,team:int,code?:string}[],
		 *   teams:array{key:int,name:string,playerCount:int}
		 *   } $data
		 */
		$data = [
			'meta'  => [
				'music' => empty($request->post['music']) ? null : $request->post['music'],
			],
			'players' => [],
			'teams' => [],
		];

		if (!empty($request->post['groupSelect'])) {
			$data['meta']['group'] = $request->post['groupSelect'];
		}

		if (!empty($request->post['tableSelect'])) {
			$data['meta']['table'] = $request->post['tableSelect'];
		}

		Timer::start('newGame.mode');
		try {
			/** @phpstan-ignore-next-line */
			$mode = GameModeFactory::getById((int)($request->getPost('game-mode', 0)));
		} catch (GameModeNotFoundException) {
		}

		if (isset($mode)) {
			$data['meta']['mode'] = $mode->loadName;
			if (!empty($request->post['variation'])) {
				$data['meta']['variations'] = [];
				/**
				 * @var int    $id
				 * @var string $suffix
				 * @phpstan-ignore-next-line
				 */
				foreach ($request->getPost('variation', []) as $id => $suffix) {
					$data['meta']['variations'][$id] = $suffix;
					$data['meta']['mode'] .= $suffix;
				}
			}
		}
		Timer::start('newGame.mode');

		/** @var array<numeric-string, int> $teams */
		$teams = [];

		// Validate and parse players
		Timer::start('newGame.players');
		/**
		 * @var int $vest
		 * @var array{name:string,team?:string,vip:numeric-string,code:string} $player
		 * @phpstan-ignore-next-line
		 */
		foreach ($request->getPost('player', []) as $vest => $player) {
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
				$data['meta']['p' . $vest . 'n'] = $player['name'];
			}
			if (!empty($player['code'])) {
				$data['meta']['p' . $vest . 'u'] = $player['code'];
			}
			$data['players'][] = [
				'vest' => (string)$vest,
				'name' => $asciiName,
				'team' => (string)$player['team'],
				'vip'  => ((int)$player['vip']) === 1,
			];
			if (!isset($teams[(string)$player['team']])) {
				$teams[(string)$player['team']] = 0;
			}
			$teams[(string)$player['team']]++;
		}
		Timer::stop('newGame.players');

		Timer::start('newGame.teams');
		/**
		 * @var int $key
		 * @var array{name:string} $team
		 * @phpstan-ignore-next-line
		 */
		foreach ($request->getPost('team', []) as $key => $team) {
			$asciiName = Strings::toAscii($team['name']);
			if ($team['name'] !== $asciiName) {
				$data['meta']['t' . $key . 'n'] = $team['name'];
			}
			$data['teams'][] = [
				'key'         => $key,
				'name'        => $asciiName,
				'playerCount' => $teams[(string)$key] ?? 0,
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


		// Choose random music ID if a group is selected
		if (isset($data['meta']['music']) && str_starts_with($data['meta']['music'], 'g-')) {
			$musicIds = array_slice(explode('-', $data['meta']['music']), 1);
			$data['meta']['music'] = $musicIds[array_rand($musicIds)];

			$data['meta']['music'] = (int)$data['meta']['music'];
		}

		// Render the game info into a load file
		Timer::start('newGame.render');
		$content = $this->latte->viewToString('gameFiles/evo5', $data);
		$loadDir = LMX_DIR . Info::get('evo5_load_file', 'games/');
		if (file_exists($loadDir) && is_dir($loadDir)) {
			file_put_contents($loadDir . '0000.game', $content);
		}
		Timer::stop('newGame.render');


		// Set up a correct music file
		Timer::start('newGame.music');
		if (isset($data['meta']['music'])) {
			try {
				/** @phpstan-ignore-next-line */
				$music = MusicMode::get($data['meta']['music']);
				if (!file_exists($music->fileName)) {
					App::getLogger()->warning('Music file does not exist - ' . $music->fileName);
				}
				else if (!copy($music->fileName, LMX_DIR . 'music/evo5.mp3')) {
					App::getLogger()->warning('Music copy failed - ' . $music->fileName);
				}
			} catch (ModelNotFoundException|ValidationException|DirectoryCreationException) {
				// Not critical, doesn't need to do anything
			}
		}
		Timer::start('newGame.music');
		Timer::stop('newGame.process');
		$this->respond(['status' => 'ok', 'mode' => $data['meta']['mode']]);
	}

}