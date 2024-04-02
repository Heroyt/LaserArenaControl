<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers\Gate;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Gate\Gate;
use App\Gate\Logic\CustomEventDto;
use App\Gate\Models\GateType;
use App\Services\EventService;
use DateTime;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Gate is a page that displays actual results and information preferably on other visible display.
 */
class GateController extends Controller
{

	public function __construct(
		Latte                 $latte,
		private readonly EventService $eventService,
		private readonly Gate $gate) {
		parent::__construct($latte);
	}

	/**
	 * @param string $gate
	 * @return ResponseInterface
	 * @throws JsonException
	 */
	public function show(string $gate = 'default') : ResponseInterface {
		$system = $_GET['system'] ?? 'all';

		$gateType = GateType::getBySlug(empty($gate) ? 'default' : $gate);
		if (!isset($gateType)) {
			return $this->respond(new ErrorDto('Gate type not found.', ErrorType::NOT_FOUND, values: ['slug' => $gate]), 404);
		}

		try {
			return $this->gate->getCurrentScreen($gateType, $system)->setParams($this->params)->run();
		} catch (ValidationException | Throwable $e) {
			return $this->respond(new ErrorDto('An error has occured', exception: $e), 500);
		}
	}

	public function setEvent(Request $request) : ResponseInterface {
		$event = $request->getPost('event', '');
		$time = (int) $request->getPost('time', 60);

		$dto = new CustomEventDto($event, time() + $time);
		Info::set('gate-event', $dto);
		$this->eventService->trigger('gate-reload', ['type' => 'custom-event', 'event' => $event, 'time' => $dto->time]);
		return $this->respond('');
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function setGateGame(Request $request) : ResponseInterface {
		$game = $this->getGame($request);
		if ($game instanceof ErrorDto) {
			return $this->respond($game, $game->type === ErrorType::NOT_FOUND ? 404 : 400);
		}
		try {
			$gateTime = time();
			Info::set('gate-game', $game);
			Info::set('gate-time', $gateTime);
			$this->eventService->trigger('gate-reload', ['type' => 'game-set', 'game' => $game->code, 'time' => $gateTime]);
		} catch (Exception $e) {
			return $this->respond(new ErrorDto('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
			                      500);
		}

		return $this->respond(['success' => true]);
	}

	/**
	 * @param Request $request
	 *
	 * @return Game|ErrorDto
	 * @throws Throwable
	 */
	private function getGame(Request $request) : Game | ErrorDto {
		$gameId = (int) $request->getPost('game', 0);
		if (empty($gameId)) {
			return new ErrorDto('Missing / Incorrect game', type: ErrorType::VALIDATION);
		}
		$system = $request->getParam('system');
		if (empty($system)) {
			return new ErrorDto('Missing / Incorrect system', type: ErrorType::VALIDATION);
		}
		$game = GameFactory::getById($gameId, ['system' => $system]);
		return $game ?? new ErrorDto('Cannot find game', type: ErrorType::NOT_FOUND);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws Throwable
	 */
	public function setGateLoaded(Request $request) : ResponseInterface {
		$game = $this->getGame($request);
		if ($game instanceof ErrorDto) {
			return $this->respond($game, $game->type === ErrorType::NOT_FOUND ? 404 : 400);
		}
		$system = $request->params['system'] ?? '';
		try {
			Info::set('gate-game', null);
			$game->fileTime = new DateTime(); // Set time to NOW
			$game->start = null;
			$game->end = null;
			Info::set($system.'-game-loaded', $game);
			$this->eventService->trigger('gate-reload',
			                             ['type' => 'game-set-loaded', 'game' => $game->code, 'time' => time()]);
		} catch (Exception $e) {
			return $this->respond(new ErrorDto('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
			                      500);
		}
		return $this->respond(['success' => true]);
	}

	/**
	 * @param string $system
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function setGateIdle(string $system = '') : ResponseInterface {
		if (empty($system)) {
			return $this->respond(new ErrorDto('Missing / Incorrect system', type: ErrorType::VALIDATION), 400);
		}
		try {
			Info::set('gate-game', null);
			Info::set($system.'-game-loaded', null);
			$this->eventService->trigger('gate-reload', ['type' => 'set-idle', 'time' => time()]);
		} catch (Exception $e) {
			return $this->respond(new ErrorDto('Failed to save the game info', type: ErrorType::DATABASE, exception: $e),
			                      500);
		}
		return $this->respond(['success' => true]);
	}

}