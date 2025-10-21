<?php

namespace App\Services;

use App\Core\App;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Enums\PrintOrientation;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\CustomResultsMode;
use App\GameModels\Game\Player;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\GameModels\Game\Team;
use App\GameModels\Game\Today;
use App\Templates\Results\ResultsParams;
use Dibi\Exception;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use InvalidArgumentException;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 *
 */
readonly class ResultPrintService
{
    public function __construct(
      private GotenbergService $gotenberg,
      private Latte            $latte
    ) {}

    /**
     * Generate PDF results for a game
     *
     * @template G of Game
     * @param  G  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int<1,max>  $copies
     * @param  bool  $cache
     *
     * @return non-empty-string File path of the generated PDF file or empty string on error
     * @throws Exception
     * @throws TemplateDoesNotExistException
     * @throws \Endroid\QrCode\Exception\ValidationException
     */
    public function getResultsPdf(
      Game   $game,
      int    $style,
      string $template,
      int    $copies = 1,
      bool   $cache = true
    ) : string {
        $pdfFile = $this->getTmpDir().$this->getResultsFileName($game, $style, $template, $copies).'.pdf';
        if ($cache && file_exists($pdfFile)) {
            return $pdfFile;
        }

        try {
            $styleObj = PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle();
        } catch (ModelNotFoundException | ValidationException) {
            $styleObj = new PrintStyle();
        }

        if ($styleObj === null) {
            throw new InvalidArgumentException('Print style '.$style.' does not exist');
        }

        $templateObj = PrintTemplate::getBySlug($template);

        $bg = ROOT.(
          $templateObj?->orientation === PrintOrientation::landscape ?
            ($styleObj->bgLandscape ?? '') :
            ($styleObj->bg ?? '')
          );

        if (!file_exists($bg)) {
            throw new Exception('Background file '.$bg.' does not exist');
        }

        $additionalFiles = [
          ROOT.'dist/main.css',
          ROOT.'dist/results/'.$template.'.css',
          $bg,
        ];

        $mode = $game->mode;
        if ($mode instanceof CustomResultsMode) {
            $customTemplate = $mode->getCustomResultsTemplate();
            if ($customTemplate !== '') {
                if (file_exists(ROOT.'dist/results/'.$customTemplate.'.css')) {
                    $additionalFiles[] = ROOT.'dist/results/'.$customTemplate.'.css';
                }
                if (file_exists(ROOT.'dist/results/'.$template.'_'.$customTemplate.'.css')) {
                    $additionalFiles[] = ROOT.'dist/results/'.$template.'_'.$customTemplate.'.css';
                }
            }
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
                                 'upload/print/',
                                 'upload/',
                                 'uploads/print/',
                                 'uploads/',
                               ],
                               ['', '', '', '', '', '', '', ''],
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
        throw new Exception('PDF generation failed');
    }

    public function getTmpDir() : string {
        $dir = TMP_DIR.'results/';
        if (is_dir($dir) || (mkdir($dir) && is_dir($dir))) {
            return $dir;
        }
        return TMP_DIR;
    }

    /**
     * @template G of Game
     * @param  G  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int<1,max>  $copies
     * @param  bool  $view
     * @return non-empty-string
     */
    public function getResultsFileName(
      Game   $game,
      int    $style,
      string $template,
      int    $copies,
      bool   $view = false
    ) : string {
        return $game->code.'-'.
          $template.'-'.
          $style.'x'.
          $copies.'.'.
          App::getShortLanguageCode().
          ($view ? '.view' : '');
    }

    /**
     * Generate html results for a game
     *
     * @template G of Game
     * @param  G  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int<1,max>  $copies
     * @param  bool  $cache
     *
     * @return non-empty-string Generated HTML
     * @throws Exception
     * @throws TemplateDoesNotExistException
     * @throws \Endroid\QrCode\Exception\ValidationException
     */
    public function getResultsHtml(
      Game   $game,
      int    $style,
      string $template,
      int    $copies = 1,
      bool   $cache = true
    ) : string {
        bdump($cache);
        $htmlFile = $this->getHtmlFilePath($game, $style, $template, $copies);
        if ($cache && file_exists($htmlFile)) {
            $html = file_get_contents($htmlFile);
            if (!empty($html)) {
                return $html;
            }
        }

        return $this->generateResultsHtml($game, $style, $template, $copies);
    }

    /**
     * @template G of Game
     * @param  G  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int<1,max>  $copies
     * @return non-empty-string
     */
    public function getHtmlFilePath(Game $game, int $style, string $template, int $copies = 1) : string {
        return $this->getTmpDir().$this->getResultsFileName($game, $style, $template, $copies).'.html';
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G  $game
     * @param  int  $style
     * @param  string  $template
     * @param  int<1,max>  $copies
     * @return non-empty-string
     * @throws TemplateDoesNotExistException|Exception|\Endroid\QrCode\Exception\ValidationException
     */
    public function generateResultsHtml(Game $game, int $style, string $template, int $copies) : string {
        $namespace = '\\App\\GameModels\\Game\\'.GameFactory::systemToNamespace($game::SYSTEM).'\\';
        $teamClass = $namespace.'Team';
        $playerClass = $namespace.'Player';
        /** @var P $player */
        $player = new $playerClass();
        /** @var T $team */
        $team = new $teamClass();

        try {
            $printStyle = PrintStyle::exists($style) ? PrintStyle::get($style) : PrintStyle::getActiveStyle();
        } catch (ModelNotFoundException | ValidationException) {
            $printStyle = new PrintStyle();
        }

        if ($printStyle === null) {
            throw new InvalidArgumentException('Print style '.$style.' does not exist');
        }

        $printTemplate = PrintTemplate::getBySlug($template);
        if ($printTemplate === null) {
            throw new InvalidArgumentException('Print template '.$template.' does not exist');
        }
        $params = new ResultsParams(
          $game,
          $printStyle,
          $printTemplate,
          new Today($game, $player, $team),
          $this->getPublicUrl($game),
          $this->getQR($game),
          App::getShortLanguageCode(),
          $copies,
        );
        $params->app = App::getInstance();

        $mode = $game->mode;
        if ($mode instanceof CustomResultsMode) {
            $customTemplate = $mode->getCustomResultsTemplate();
            if ($customTemplate !== '') {
                $customFile = TEMPLATE_DIR.'results/templates/'.$template.'/'.$customTemplate.'.latte';
                if (file_exists($customFile)) {
                    $template .= '/'.$customTemplate;
                }
                elseif (file_exists(TEMPLATE_DIR.'results/templates/'.$customTemplate.'.latte')) {
                    $template = $customTemplate;
                }
            }
        }

        try {
            /** @var non-empty-string $html */
            $html = $this->latte->viewToString('results/templates/'.$template, $params);
        } catch (TemplateDoesNotExistException) {
            /** @var non-empty-string $html */
            $html = $this->latte->viewToString('results/templates/default', $params);
        }

        file_put_contents($this->getHtmlFilePath($game, $style, $template, $copies), $html);
        return $html;
    }

    /**
     * @template G of Game
     * @param  G  $game
     * @return non-empty-string
     */
    public function getPublicUrl(Game $game) : string {
        /** @var string $url */
        $url = Info::get('liga_api_url');
        return trailingSlashIt($url).'g/'.$game->code.'?mtm_campaign=QR&mtm_kwd=print';
    }

    /**
     * Get SVG QR code for game
     *
     * @template G of Game
     * @param  G  $game
     *
     * @return non-empty-string
     * @throws \Endroid\QrCode\Exception\ValidationException
     */
    public function getQR(Game $game) : string {
        $result = new Builder(
          writer              : new SvgWriter(),
          data                : $this->getPublicUrl($game),
          encoding            : new Encoding('UTF-8'),
          errorCorrectionLevel: ErrorCorrectionLevel::Low
        )->build();
        $qr = $result->getString();
        assert(!empty($qr));
        return $qr;
    }
}
