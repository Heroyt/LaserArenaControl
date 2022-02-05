<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Exceptions\TemplateDoesNotExistException;
use App\Models\Factory\GameFactory;
use App\Models\Game\Game;
use App\Models\Game\PrintStyle;
use App\Models\Game\PrintTemplate;
use App\Models\Game\Today;
use App\Tools\Strings;

class Results extends Controller
{

	protected string $title       = 'Results';
	protected string $description = '';

	public function show(Request $request) : void {
		$rows = GameFactory::queryGames(true)->orderBy('start')->desc()->limit(10)->fetchAll();
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
				$this->params['games'][] = $this->params['selected'];
			}
		}
		foreach ($rows as $row) {
			$this->params['games'][] = GameFactory::getByCode($row->code);
		}
		usort($this->params['games'], static function(Game $game1, Game $game2) {
			return $game2->start->getTimestamp() - $game1->start->getTimestamp();
		});
		if (!isset($this->params['selected'])) {
			$this->params['selected'] = $this->params['games'][0] ?? null;
		}
		$this->params['selectedStyle'] = (int) ($_GET['style'] ?? PrintStyle::getActiveStyleId());
		$this->params['selectedTemplate'] = $_GET['template'] ?? 'default';
		$this->params['styles'] = PrintStyle::getAll();
		$this->params['templates'] = PrintTemplate::getAll();
		bdump($this->params);
		$this->view('pages/results/index');
	}

	public function printGame(Request $request) : void {
		$code = $request->params['code'] ?? '';
		$this->params['copies'] = (int) ($request->params['copies'] ?? 1);
		$template = $request->params['template'] ?? 'default';
		$style = (int) ($request->params['style'] ?? PrintStyle::getActiveStyleId());
		$this->params['colorless'] = ($request->params['type'] ?? 'color') === 'colorless';

		// Get game
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			http_response_code(404);
			$this->view('results/gameDoesNotExist');
			return;
		}

		$this->params['game'] = $game;
		bdump($game->getPlayers());
		$this->params['style'] = PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle();
		$this->params['template'] = PrintTemplate::query()->where('slug = %s', $template)->first();
		$namespace = '\\App\\Models\\Game\\'.Strings::toPascalCase($game::SYSTEM).'\\';
		$teamClass = $namespace.'Team';
		$playerClass = $namespace.'Player';
		$this->params['today'] = new Today($game, new $playerClass, new $teamClass);

		try {
			$this->view('results/templates/'.$template);
		} catch (TemplateDoesNotExistException $e) {
			$this->view('results/templates/default');
		}
	}

}