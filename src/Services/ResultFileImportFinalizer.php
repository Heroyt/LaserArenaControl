<?php

declare(strict_types=1);

namespace App\Services;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\App;
use App\GameModels\Game\Game;
use App\Services\LaserLiga\LigaApi;
use Dibi\Exception;
use Lsr\Logging\Logger;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Symfony\Component\Console\Output\OutputInterface;

readonly class ResultFileImportFinalizer
{
    public function __construct(
        private EventService     $eventService,
        private LigaApi          $ligaApi,
        private FeatureConfig    $featureConfig,
        private GameStateStorage $gameStateStorage,
    ) {
    }

    public function triggerImported(int $count): void {
        if ($count > 0) {
            $this->eventService->trigger('game-imported', ['count' => $count]);
        }
    }

    /**
     * @template G of Game
     * @param G $game
     * @throws Exception
     */
    public function triggerUnfinished(
        Game             $game,
        string           $event,
        Logger           $logger,
        ?OutputInterface $output = null,
    ): void {
        if ($event !== 'game-started' && $event !== 'game-loaded') {
            $logger->warning('Skipping unknown unfinished game event.', ['event' => $event]);
            return;
        }

        $key = $game::SYSTEM . '-' . $event;
        $storedGame = $this->gameStateStorage->get($key);
        if (
            $storedGame instanceof Game
            && $this->getUnfinishedGameTimestamp($storedGame, $event) > $this->getUnfinishedGameTimestamp($game, $event)
        ) {
            $logger->debug(
                'Keeping newer unfinished game state.',
                [
                    'key' => $key,
                    'storedGame' => $storedGame->resultsFile,
                    'game' => $game->resultsFile,
                ],
            );
            return;
        }

        $logger->debug('Setting unfinished game state.', ['key' => $key, 'game' => $game->resultsFile]);
        $output?->writeln('Setting unfinished game state: "' . $key . '" - ' . $game->resultsFile);

        $this->gameStateStorage->set($key, $game);
        $this->eventService->trigger($event, ['game' => $game->resultsFile]);
    }

    private function getUnfinishedGameTimestamp(Game $game, string $event): int {
        if ($event === 'game-started') {
            return $game->start?->getTimestamp() ?? $game->fileTime?->getTimestamp() ?? 0;
        }

        return $game->fileTime?->getTimestamp() ?? $game->start?->getTimestamp() ?? 0;
    }

    /**
     * @template G of Game
     * @param list<G> $finishedGames
     */
    public function finalize(array $finishedGames, Logger $logger, ?OutputInterface $output = null): void {
        if (empty($finishedGames)) {
            $logger->info('No games to synchronize to public');
            return;
        }

        if ($this->featureConfig->isFeatureEnabled('liga')) {
            $gamesBySystem = [];
            foreach ($finishedGames as $finishedGame) {
                $gamesBySystem[$finishedGame::SYSTEM][] = $finishedGame;
            }

            foreach ($gamesBySystem as $system => $games) {
                if ($this->ligaApi->syncGames($system, $games)) {
                    $logger->info('Synchronized games to public.', ['system' => $system, 'count' => count($games)]);
                    foreach ($games as $finishedGame) {
                        $finishedGame->sync = true;
                        try {
                            $finishedGame->save();
                        } catch (ValidationException $e) {
                            $output?->writeln(
                                Colors::color(ForegroundColors::RED) .
                                'Failed to save finished game after synchronization. ' . $e->getMessage() .
                                Colors::reset(),
                            );
                            $logger->warning(
                                'Failed to save finished game after synchronization',
                                ['system' => $system, 'game' => $finishedGame->code, 'exception' => $e->getMessage()],
                            );
                            $logger->exception($e);
                        }
                    }
                    continue;
                }

                $logger->warning('Failed to synchronize games to public', ['system' => $system]);
                $output?->writeln(
                    Colors::color(ForegroundColors::RED) .
                    'Failed to synchronize games to public' .
                    Colors::reset(),
                );
            }
        }

        /** @var ResultsPrecacheService $precacheService */
        $precacheService = App::getService('resultPrecache');
        $precacheService->prepareGamePrecache(
            ...array_map(static fn (Game $game): string => $game->code, $finishedGames),
        );
    }
}
