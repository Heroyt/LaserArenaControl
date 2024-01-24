<?php

namespace App\Services;

use App\Core\App;
use App\Core\Info;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Helpers\Tools\Strings;

/**
 *
 */
readonly class ResultPrintService
{

	public function __construct(
		private GotenbergService $gotenberg,
		private Latte            $latte
	) {
	}

	/**
	 * Generate PDF results for a game
	 *
	 * @param Game   $game
	 * @param int    $style
	 * @param string $template
	 * @param int    $copies
	 * @param bool   $cache
	 *
	 * @return string File path of the generated PDF file or empty string on error
	 */
	public function getResultsPdf(Game $game, int $style, string $template, int $copies, bool $cache = true): string {
		$pdfFile = $this->getTmpDir() . $this->getResultsFileName($game, $style, $template, $copies) . '.pdf';
		if ($cache && file_exists($pdfFile)) {
			return $pdfFile;
		}
		$content = $this->gotenberg->chromium->getFromUrl(
			str_replace(
				App::getUrl(),
				'http://web/',
				App::getLink(
					[
						'results',
						$game->code,
						'print',
						App::getShortLanguageCode(),
						$copies,
						$style,
						$template,
						'html'    => '1',
						'view'    => '1',
						'noCache' => '1',
					],
				)
			)
		);
		if (!empty($content)) {
			file_put_contents($pdfFile, $content);
			return $pdfFile;
		}
		return '';
	}

	public function getTmpDir(): string {
		$dir = TMP_DIR . 'results/';
		if (is_dir($dir) || (mkdir($dir) && is_dir($dir))) {
			return $dir;
		}
		return TMP_DIR;
	}

	public function getResultsFileName(Game $game, int $style, string $template, int $copies): string {
		return $game->code . '-' . $template . '-' . $style . 'x' . $copies . '.' . App::getShortLanguageCode();
	}

	/**
	 * Generate html results for a game
	 *
	 * @param Game   $game
	 * @param int    $style
	 * @param string $template
	 * @param int    $copies
	 * @param bool   $cache
	 *
	 * @return string Generated HTML
	 * @throws ModelNotFoundException
	 * @throws TemplateDoesNotExistException
	 * @throws ValidationException
	 */
	public function getResultsHtml(Game $game, int $style, string $template, int $copies = 1, bool $cache = true): string {
		$htmlFile = $this->getTmpDir() . $this->getResultsFileName($game, $style, $template, $copies) . '.html';
		if ($cache && file_exists($htmlFile)) {
			$html = file_get_contents($htmlFile);
			if ($html !== false) {
				return $html;
			}
		}

		$namespace = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($game::SYSTEM) . '\\';
		$teamClass = $namespace . 'Team';
		$playerClass = $namespace . 'Player';
		/** @var Player $player */
		$player = new $playerClass;
		/** @var Team $team */
		$team = new $teamClass;

		$params = [
			'copies'    => $copies,
			'game'      => $game,
			'style'     => PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle(),
			'template'  => PrintTemplate::query()->where('slug = %s', $template)->first(),
			'today'     => new Today($game, $player, $team),
			'publicUrl' => $this->getPublicUrl($game),
			'qr'        => $this->getQR($game),
			'lang' => App::getShortLanguageCode(),
		];

		try {
			$html = $this->latte->viewToString('results/templates/' . $template, $params);
		} catch (TemplateDoesNotExistException) {
			$html = $this->latte->viewToString('results/templates/default', $params);
		}

		file_put_contents($htmlFile, $html);
		return $html;
	}

	public function getPublicUrl(Game $game): string {
		/** @var string $url */
		$url = Info::get('liga_api_url');
		return trailingSlashIt($url) . 'g/' . $game->code;
	}

	/**
	 * Get SVG QR code for game
	 *
	 * @param Game $game
	 *
	 * @return string
	 */
	public function getQR(Game $game): string {
		$result = Builder::create()
		                 ->data($this->getPublicUrl($game))
		                 ->writer(new SvgWriter())
		                 ->encoding(new Encoding('UTF-8'))
		                 ->errorCorrectionLevel(ErrorCorrectionLevel::Low)
		                 ->build();
		return $result->getString();
	}

}