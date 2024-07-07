<?php

namespace App\Services;

use App\Core\App;
use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\PrintStyle;
use App\GameModels\Game\Team;
use App\Tasks\GameHighlightsTask;
use App\Tasks\GamePrecacheTask;
use App\Tasks\Payloads\GameHighlightsPayload;
use App\Tasks\Payloads\GamePrecachePayload;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Redis;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Throwable;

/**
 *
 */
readonly class ResultsPrecacheService
{
    public const string KEY = 'result-precache-queue';

    public function __construct(
        private Redis              $redis,
        private ResultPrintService $printService,
        private TaskProducer       $taskProducer,
        private string             $mode = 'cron',
    ) {
    }

    /**
     * Put game codes into a precache queue
     *
     * @param  string  ...$codes
     *
     * @return int|false
     * @throws JobsException
     */
    public function prepareGamePrecache(string ...$codes): int | false {
        if ($this->mode === 'queue') {
            foreach ($codes as $code) {
                $this->taskProducer->plan(GamePrecacheTask::class, new GamePrecachePayload($code));
                $this->taskProducer->plan(GameHighlightsTask::class, new GameHighlightsPayload($code));
            }
            $this->taskProducer->dispatch();
            return count($codes);
        }
        return $this->redis->lPush($this::KEY, ...$codes);
    }

    /**
     * Precache next game results in precache queue
     *
     * @return bool True if a game is precached, false on error or if there is no game to precache
     */
    public function precacheNextGame(?int $style = null, ?string $template = null): bool {
        $code = $this->redis->lPop($this::KEY);
        if (empty($code)) {
            return false;
        }

        return $this->precacheGameByCode($code, $style, $template);
    }

    /**
     * Precache game by its code
     *
     * @param  string  $code
     * @param  int|null  $style
     * @param  string|null  $template
     * @return bool False if game not found or if pre-caching failed
     */
    public function precacheGameByCode(string $code, ?int $style = null, ?string $template = null): bool {
        try {
            $game = GameFactory::getByCode($code);
        } catch (Throwable) {
        }
        if (!isset($game)) {
            return false;
        }
        return $this->precacheGame($game, $style, $template);
    }

    /**
     * Precache game results PDF
     *
     * @param  Game<Team, Player>  $game
     * @param  int|null  $style
     * @param  string|null  $template
     * @return bool False if pre-caching failed
     */
    private function precacheGame(Game $game, ?int $style = null, ?string $template = null): bool {
        try {
            $file = $this->printService->getResultsPdf(
                $game,
                $style ?? PrintStyle::getActiveStyleId(),
                $template ?? ((string) Info::get('default_print_template', 'default')),
            );
        } catch (TemplateDoesNotExistException $e) {
            App::getInstance()->getLogger()->exception($e);
            return false;
        }
        return $file !== '' && file_exists($file);
    }
}
