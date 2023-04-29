<?php

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Writer\SvgWriter;
use Lsr\Core\App;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;

/**
 *
 */
trait CommonGateMethods
{

	protected ?Game $game = null;

	/**
	 * Display the results of the game
	 *
	 * @pre Gate::$game must be set
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 */
	protected function getResults(): void {
		if (!isset($this->game)) {
			App::redirect(['E404']);
		}
		$this->params['game'] = $this->game;
		$this->params['qr'] = $this->getQR($this->game);
		$namespace = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($this->game::SYSTEM) . '\\';
		$teamClass = $namespace . 'Team';
		$playerClass = $namespace . 'Player';
		/** @var Player $player */
		$player = new $playerClass;
		/** @var Team $team */
		$team = new $teamClass;
		$this->params['today'] = new Today($this->game, $player, $team);
		if (isset($this->game->mode) && $this->game->mode instanceof CustomResultsMode) {
			$this->view(
				$this->game->mode->getCustomGateTemplate($this)
			);
		} else {
			$this->view('pages/gate/results');
		}
	}

	/**
	 * Get SVG QR code for game
	 *
	 * @param Game $game
	 *
	 * @return string
	 */
	protected function getQR(Game $game): string {
		$result = Builder::create()
			->data($this->getPublicUrl($game))
			->writer(new SvgWriter())
			->encoding(new Encoding('UTF-8'))
			->errorCorrectionLevel(new ErrorCorrectionLevelLow())
			->build();
		return $result->getString();
	}

	protected function getPublicUrl(Game $game): string {
		/** @var string $url */
		$url = Info::get('liga_api_url');
		return trailingSlashIt($url) . 'g/' . $game->code;
	}
}