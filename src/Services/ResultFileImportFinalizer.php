<?php

declare(strict_types=1);

namespace App\Services;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\GameModels\Game\Game;
use App\Services\LaserLiga\LigaApi;
use Lsr\Logging\Logger;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Symfony\Component\Console\Output\OutputInterface;

readonly class ResultFileImportFinalizer
{
    public function __construct(
        private EventService  $eventService,
        private LigaApi       $ligaApi,
        private FeatureConfig $featureConfig,
    )
    {
    }

    public function triggerImported(int $count): void
    {
        if ($count > 0) {
            $this->eventService->trigger('game-imported', ['count' => $count]);
        }
    }

    /**
     * @template G of Game
     * @param list<G> $finishedGames
     */
    public function finalize(array $finishedGames, Logger $logger, ?OutputInterface $output = null): void
    {
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
                                Colors::reset()
                            );
                            $logger->warning(
                                'Failed to save finished game after synchronization',
                                ['system' => $system, 'game' => $finishedGame->code, 'exception' => $e->getMessage()]
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
                    Colors::reset()
                );
            }
        }

        /** @var ResultsPrecacheService $precacheService */
        $precacheService = \App\Core\App::getService('resultPrecache');
        $precacheService->prepareGamePrecache(
            ...array_map(static fn(Game $game): string => $game->code, $finishedGames)
        );
    }
}
