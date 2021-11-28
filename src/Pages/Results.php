<?php

namespace App\Pages;

use App\Core\Page;
use App\Core\Request;
use App\Exceptions\TemplateDoesNotExistException;
use App\Models\Factory\GameFactory;
use App\Models\Game\PrintStyle;
use App\Models\Game\Today;
use App\Tools\Strings;

class Results extends Page
{

	protected string $title       = 'Results';
	protected string $description = '';

	public function show() : void {
		$this->view('pages/dashboard/index');
	}

	public function printGame(Request $request) : void {
		$code = $request->params['code'] ?? '';
		$lang = $request->params['lang'] ?? DEFAULT_LANGUAGE;
		$copies = $request->params['copies'] ?? 1;
		$template = $request->params['template'] ?? 'default';
		$style = $request->params['style'] ?? PrintStyle::getActiveStyle();

		// Get game
		$game = GameFactory::getByCode($code);
		if (!isset($game)) {
			http_response_code(404);
			$this->view('results/gameDoesNotExist');
			return;
		}

		try {
			$this->params['game'] = $game;
			$this->params['style'] = $style;
			$namespace = '\\App\\Models\\Game\\'.Strings::toPascalCase($game::SYSTEM).'\\';
			$teamClass = $namespace.'Team';
			$playerClass = $namespace.'Player';
			$this->params['today'] = new Today($game, new $playerClass, new $teamClass);
			$this->view('results/templates/'.$template);
		} catch (TemplateDoesNotExistException $e) {
			$this->view('results/templates/default');
		}
	}

}