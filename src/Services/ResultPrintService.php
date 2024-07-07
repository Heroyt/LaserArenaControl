<?php

namespace App\Services;

use App\Core\App;
use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Enums\PrintOrientation;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use App\Templates\Results\ResultsParams;
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
     * @param  Game<Team, Player>  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int  $copies
     * @param  bool  $cache
     *
     * @return string File path of the generated PDF file or empty string on error
     * @throws TemplateDoesNotExistException
     */
    public function getResultsPdf(
        Game   $game,
        int    $style,
        string $template,
        int    $copies = 1,
        bool   $cache = true
    ): string {
        $pdfFile = $this->getTmpDir() . $this->getResultsFileName($game, $style, $template, $copies) . '.pdf';
        if ($cache && file_exists($pdfFile)) {
            return $pdfFile;
        }

        try {
            $styleObj = PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle();
        } catch (ModelNotFoundException | ValidationException) {
            $styleObj = new PrintStyle();
        }
        $templateObj = PrintTemplate::getBySlug($template);

        $bg = ROOT . ($templateObj?->orientation === PrintOrientation::landscape ? $styleObj->bgLandscape :
            $styleObj->bg);

        $additionalFiles = [
          ROOT . 'dist/main.css',
          ROOT . 'dist/results/' . $template . '.css',
          $bg,
        ];

        try {
            $mode = $game->getMode();
            if ($mode instanceof CustomResultsMode) {
                $customTemplate = $mode->getCustomResultsTemplate();
                if ($customTemplate !== '') {
                    if (file_exists(ROOT . 'dist/results/' . $customTemplate . '.css')) {
                        $additionalFiles[] = ROOT . 'dist/results/' . $customTemplate . '.css';
                    }
                    if (file_exists(ROOT . 'dist/results/' . $template . '_' . $customTemplate . '.css')) {
                        $additionalFiles[] = ROOT . 'dist/results/' . $template . '_' . $customTemplate . '.css';
                    }
                }
            }
        } catch (GameModeNotFoundException) {
        }

        $content = $this
          ->gotenberg
          ->chromium
          ->getFromHTML(
              str_replace(
                  [
                                 App::getInstance()->getBaseUrl(),
                                 'dist/results/',
                                 'dist/',
                                 'assets/images/print/',
                                 'upload/',
                                 ],
                  ['', '', '', '', ''],
                  $this->getResultsHtml(
                      $game,
                      $style,
                      $template,
                      $copies,
                      $cache
                  )
              ),
              additionalFiles: $additionalFiles,
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

    /**
     * @param  Game<Team, Player>  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int  $copies
     * @param  bool  $view
     * @return string
     */
    public function getResultsFileName(
        Game   $game,
        int    $style,
        string $template,
        int    $copies,
        bool   $view = false
    ): string {
        return $game->code . '-' .
          $template . '-' .
          $style . 'x' .
          $copies . '.' .
          App::getShortLanguageCode() .
          ($view ? '.view' : '');
    }

    /**
     * Generate html results for a game
     *
     * @param  Game<Team, Player>  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int  $copies
     * @param  bool  $cache
     *
     * @return string Generated HTML
     * @throws TemplateDoesNotExistException
     */
    public function getResultsHtml(
        Game   $game,
        int    $style,
        string $template,
        int    $copies = 1,
        bool   $cache = true
    ): string {
        bdump($cache);
        $htmlFile = $this->getHtmlFilePath($game, $style, $template, $copies);
        if ($cache && file_exists($htmlFile)) {
            $html = file_get_contents($htmlFile);
            if ($html !== false) {
                return $html;
            }
        }

        return $this->generateResultsHtml($game, $style, $template, $copies);
    }

    /**
     * @param  Game<Team, Player>  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int  $copies
     * @return string
     */
    public function getHtmlFilePath(Game $game, int $style, string $template, int $copies = 1): string {
        return $this->getTmpDir() . $this->getResultsFileName($game, $style, $template, $copies) . '.html';
    }

    /**
     * @param  Game<Team, Player>  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int  $copies
     * @return string
     * @throws TemplateDoesNotExistException
     */
    public function generateResultsHtml(Game $game, int $style, string $template, int $copies): string {
        $namespace = '\\App\\GameModels\\Game\\' . Strings::toPascalCase($game::SYSTEM) . '\\';
        $teamClass = $namespace . 'Team';
        $playerClass = $namespace . 'Player';
        /** @var Player<Game, Team> $player */
        $player = new $playerClass();
        /** @var Team<Player, Game> $team */
        $team = new $teamClass();

        try {
            $printStyle = PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle();
        } catch (ModelNotFoundException | ValidationException) {
            $printStyle = new PrintStyle();
        }

        $params = new ResultsParams(
            $game,
            $printStyle,
            PrintTemplate::getBySlug($template),
            new Today($game, $player, $team),
            $this->getPublicUrl($game),
            $this->getQR($game),
            App::getShortLanguageCode(),
            $copies,
        );
        $params->app = App::getInstance();

        try {
            $mode = $game->getMode();
            if ($mode instanceof CustomResultsMode) {
                $customTemplate = $mode->getCustomResultsTemplate();
                if ($customTemplate !== '') {
                    $customFile = TEMPLATE_DIR . 'results/templates/' . $template . '/' . $customTemplate . '.latte';
                    if (file_exists($customFile)) {
                        $template .= '/' . $customTemplate;
                    } elseif (file_exists(TEMPLATE_DIR . 'results/templates/' . $customTemplate . '.latte')) {
                        $template = $customTemplate;
                    }
                }
            }
        } catch (GameModeNotFoundException) {
        }

        try {
            $html = $this->latte->viewToString('results/templates/' . $template, $params);
        } catch (TemplateDoesNotExistException) {
            $html = $this->latte->viewToString('results/templates/default', $params);
        }

        file_put_contents($this->getHtmlFilePath($game, $style, $template, $copies), $html);
        return $html;
    }

    /**
     * @param  Game<Team, Player>  $game
     * @return string
     */
    public function getPublicUrl(Game $game): string {
        /** @var string $url */
        $url = Info::get('liga_api_url');
        return trailingSlashIt($url) . 'g/' . $game->code;
    }

    /**
     * Get SVG QR code for game
     *
     * @param  Game<Team, Player>  $game
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
