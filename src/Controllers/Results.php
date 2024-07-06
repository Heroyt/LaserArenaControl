<?php

namespace App\Controllers;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\PrintTemplate;
use App\Services\ResultPrintService;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Metrics\Metrics;
use Throwable;
use Tracy\Debugger;

/**
 *
 */
class Results extends Controller
{
    protected string $title = 'Results';
    protected string $description = '';

    public function __construct(
        private readonly ResultPrintService $printService,
        private readonly Metrics            $metrics,
    ) {
        parent::__construct();
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws TemplateDoesNotExistException
     * @throws Throwable
     * @throws ValidationException
     */
    public function show(Request $request): ResponseInterface {
        $rows = GameFactory::queryGames(true)->orderBy('start')->desc()->limit(10)->fetchAll(cache: false);
        if (count($rows) === 0) {
            return $this->view('pages/results/noGames');
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
        usort(
            $this->params['games'],
            static function (?Game $game1, ?Game $game2) {
                return $game2?->start?->getTimestamp() - $game1?->start?->getTimestamp();
            }
        );
        if (!isset($this->params['selected'])) {
            $this->params['selected'] = $this->params['games'][0] ?? null;
        }
        $this->params['selectedStyle'] = (int) $request->getGet('style', PrintStyle::getActiveStyleId());
        $this->params['selectedTemplate'] = $request->getGet(
            'template',
            Info::get('default_print_template', 'default')
        );
        $this->params['styles'] = PrintStyle::getAll();
        $this->params['templates'] = PrintTemplate::getAll();
        return $this->view('pages/results/index');
    }

    /**
     * @throws Throwable
     * @throws DirectoryCreationException
     * @throws ModelNotFoundException
     * @throws ValidationException
     * @throws TemplateDoesNotExistException
     */
    public function printGame(
        Request $request,
        string  $code = '',
        int     $copies = 1,
        string  $template = 'default',
        ?int    $style = null
    ): ResponseInterface {
        $style ??= PrintStyle::getActiveStyleId();
        $cache = !$request->getGet('nocache', false);
        //$colorless = ($request->params['type'] ?? 'color') === 'colorless';

        $game = $code === 'last' ? GameFactory::getLastGame() : GameFactory::getByCode($code);

        if (!isset($game)) {
            $this->respond('Game not found', 404);
        }

        if (!$request->getGet('html', false)) {
            $pdfFile = $this->printService->getResultsPdf($game, $style, $template, $copies, $cache);
            if ($pdfFile !== '' && file_exists($pdfFile)) {
                $this->metrics->add('results_printed', $copies, [$this->getApp()::getShortLanguageCode(), $template]);
                $this->metrics->add('games_printed', 1, [$this->getApp()::getShortLanguageCode(), $template]);
                return new Response(
                    200,
                    ['Content-Type' => 'application/pdf;filename=results.pdf'],
                    fopen($pdfFile, 'rb')
                );
            }
        }
        Debugger::$showBar = (bool) $request->getGet('tracy', false);
        return $this->respond(
            $this->printService->getResultsHtml($game, $style, $template, $copies, $cache)
        );
    }
}
