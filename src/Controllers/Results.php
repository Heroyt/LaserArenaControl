<?php

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use App\Services\GotenbergService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Writer\SvgWriter;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Throwable;
use Tracy\Debugger;

class Results extends Controller
{

	protected string $title       = 'Results';
	protected string $description = '';

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 * @throws Throwable
	 */
	public function show(Request $request) : void {
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
		usort($this->params['games'], static function(Game $game1, Game $game2) {
			return $game2->start?->getTimestamp() - $game1->start?->getTimestamp();
		});
		if (!isset($this->params['selected'])) {
			/** @phpstan-ignore-next-line */
			$this->params['selected'] = $this->params['games'][0] ?? null;
		}
		$this->params['selectedStyle'] = (int) ($_GET['style'] ?? PrintStyle::getActiveStyleId());
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
	public function printGame(Request $request) : void {
		$code = $request->params['code'] ?? '';
		$this->params['copies'] = (int) ($request->params['copies'] ?? 1);
		$template = $request->params['template'] ?? 'default';
		$style = (int) ($request->params['style'] ?? PrintStyle::getActiveStyleId());
		$this->params['colorless'] = ($request->params['type'] ?? 'color') === 'colorless';

		if (!isset($request->get['html'])) {
			$pdfFile = $this->getTmpDir().$code.'-'.$template.'-'.$style.'x'.$this->params['copies'].'.'.App::getShortLanguageCode().'.pdf';
			if (file_exists($pdfFile)) {
				header('Content-type: application/pdf;filename=results.pdf');
				$this->respond(file_get_contents($pdfFile));
			}
			/** @var GotenbergService $gotenberg */
			$gotenberg = App::getService('gotenberg');
			$content = $gotenberg->chromium->getFromUrl(
				str_replace(App::getUrl(), 'http://web/', App::getLink(
					[
						'results',
						$code,
						'print',
						App::getShortLanguageCode(),
						$this->params['copies'],
						$style,
						$template,
						'html'    => '1',
						'view'    => '1',
						'noCache' => '1',
					],
				))
			);
			if (!empty($content)) {
				file_put_contents($pdfFile, $content);
				header('Content-type: application/pdf;filename=results.pdf');
				$this->respond($content);
			}
		}
		Debugger::$showBar = false;
		echo $this->getResultsHtml($code, $style, $template);
	}

	protected function getTmpDir() : string {
		$dir = TMP_DIR.'results/';
		if (is_dir($dir) || (mkdir($dir) && is_dir($dir))) {
			return $dir;
		}
		return TMP_DIR;
	}

	private function getPublicUrl(Game $game) : string {
		/** @var string $url */
		$url = Info::get('liga_api_url');
		return trailingSlashIt($url).'g/'.$game->code;
	}

	/**
	 * Get SVG QR code for game
	 *
	 * @param Game $game
	 *
	 * @return string
	 */
	private function getQR(Game $game) : string {
		$result = Builder::create()
										 ->data($this->getPublicUrl($game))
										 ->writer(new SvgWriter())
										 ->encoding(new Encoding('UTF-8'))
										 ->errorCorrectionLevel(new ErrorCorrectionLevelLow())
										 ->build();
		return $result->getString();
	}

	/**
	 * @param string $code
	 * @param int    $style
	 * @param string $template
	 *
	 * @return string
	 * @throws DirectoryCreationException
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws Throwable
	 * @throws ValidationException
	 */
	protected function getResultsHtml(string $code, int $style, string $template) : string {
		$htmlFile = $this->getTmpDir().$code.'-'.$template.'-'.$style.'x'.$this->params['copies'].'.'.App::getShortLanguageCode().'.html';
		if (!isset($_GET['noCache']) && file_exists($htmlFile)) {
			$html = file_get_contents($htmlFile);
			if ($html !== false) {
				return $html;
			}
		}
		// Get game
		$game = $code === 'last' ? GameFactory::getLastGame() : GameFactory::getByCode($code);
		if (!isset($game)) {
			http_response_code(404);
			$this->view('results/gameDoesNotExist');
			exit;
		}

		$this->params['game'] = $game;
		$this->params['style'] = PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle();
		$this->params['template'] = PrintTemplate::query()->where('slug = %s', $template)->first();
		$namespace = '\\App\\GameModels\\Game\\'.Strings::toPascalCase($game::SYSTEM).'\\';
		$teamClass = $namespace.'Team';
		$playerClass = $namespace.'Player';
		/** @var Player $player */
		$player = new $playerClass;
		/** @var Team $team */
		$team = new $teamClass;
		$this->params['today'] = new Today($game, $player, $team);
		$this->params['publicUrl'] = $this->getPublicUrl($game);
		$this->params['qr'] = $this->getQR($game);

		try {
			$html = $this->latte->viewToString('results/templates/'.$template, $this->params);
		} catch (TemplateDoesNotExistException) {
			$html = $this->latte->viewToString('results/templates/default', $this->params);
		}

		file_put_contents($htmlFile, $html);
		return $html;
	}

}