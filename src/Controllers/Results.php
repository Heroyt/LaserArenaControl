<?php

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\Services\ResultPrintService;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;
use Tracy\Debugger;

class Results extends Controller
{

	protected string $title       = 'Results';
	protected string $description = '';

	public function __construct(Latte $latte, private readonly ResultPrintService $printService) {
		parent::__construct($latte);
	}

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	public function show(Request $request): void {
		$rows = GameFactory::queryGames(true)->orderBy('start')->desc()->limit(10)->fetchAll(cache: false);
		if (count($rows) === 0) {
			$this->view('pages/results/noGames');
			return;
		}
		$this->params['games'] = [];
		if (isset($request->params['code'])) {
			$this->params['selected'] = GameFactory::getByCode($request->params['code']);
			// Check if game already exists
			$found = false;
			foreach ($rows as $row) {
				if ($row->code === $request->params['code']) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				/** @phpstan-ignore-next-line */
				$this->params['games'][] = $this->params['selected'];
			}
		}
		foreach ($rows as $row) {
			/** @phpstan-ignore-next-line */
			$this->params['games'][] = GameFactory::getByCode($row->code);
		}
		/** @phpstan-ignore-next-line */
		usort($this->params['games'], static function (Game $game1, Game $game2) {
			return $game2->start?->getTimestamp() - $game1->start?->getTimestamp();
		});
		if (!isset($this->params['selected'])) {
			/** @phpstan-ignore-next-line */
			$this->params['selected'] = $this->params['games'][0] ?? null;
		}
		$this->params['selectedStyle'] = (int)($_GET['style'] ?? PrintStyle::getActiveStyleId());
		$this->params['selectedTemplate'] = $_GET['template'] ?? Info::get('default_print_template', 'default');
		$this->params['styles'] = PrintStyle::getAll();
		$this->params['templates'] = PrintTemplate::getAll();
		$this->view('pages/results/index');
	}

	/**
	 * @throws Throwable
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws TemplateDoesNotExistException
	 */
	public function printGame(Request $request): void {
		$code = (string)($request->params['code'] ?? '');
		$copies = (int)($request->params['copies'] ?? 1);
		$template = (string)($request->params['template'] ?? 'default');
		$style = (int)($request->params['style'] ?? PrintStyle::getActiveStyleId());
		$cache = !isset($request->get['noCache']);
		//$colorless = ($request->params['type'] ?? 'color') === 'colorless';

		$game = $code === 'last' ? GameFactory::getLastGame() : GameFactory::getByCode($code);

		if (!isset($game)) {
			$this->respond('Game not found', 404);
		}

		if (!isset($request->get['html'])) {
			$pdfFile = $this->printService->getResultsPdf($game, $style, $template, $copies, $cache);
			if ($pdfFile !== '' && file_exists($pdfFile)) {
				header('Content-type: application/pdf;filename=results.pdf');
				$this->respond(file_get_contents($pdfFile));
			}
		}
		Debugger::$showBar = false;
		echo $this->printService->getResultsHtml($game, $style, $template, $copies, $cache);
	}

}