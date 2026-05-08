<?php

declare(strict_types=1);

namespace App\Services;

use App\Cli\Colors;
use App\Cli\Enums\ForegroundColors;
use App\Core\Info;
use App\DataObjects\Import\ResultFileImportResult;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use DateTimeInterface;
use Lsr\Caching\Cache;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Logging\Logger;
use Symfony\Component\Console\Output\OutputInterface;

readonly class ResultFileImporter
{
    public function __construct(
        private Cache $cache,
    )
    {
    }

    /**
     * @template G of Game
     * @param AbstractResultsParser<G> $parser
     */
    public function import(
        AbstractResultsParser $parser,
        string                $system,
        string                $file,
        int                   $now,
        int                   $gameLoadedTime,
        Logger                $logger,
        ?OutputInterface      $output = null,
    ): ResultFileImportResult
    {
        $logger->debug('Preparing parser for file', ['file' => $file, 'system' => $system]);
        $parser->setFile($file);
        $logger->debug('Starting parser->parse()', ['file' => $file, 'system' => $system]);
        $game = $parser->parse();
        $logger->debug(
            'Finished parser->parse()',
            [
                'file' => $file,
                'system' => $system,
                'code' => $game->code ?? null,
                'finished' => $game->isFinished(),
            ]
        );

        $isStarted = $game->isStarted();
        $isUpdated = isset($game->fileTime) && ($now - $game->fileTime->getTimestamp()) <= $gameLoadedTime;

        if (!$game->isFinished()) {
            $logger->debug('Game is not finished');
            $output?->writeln('Game is not finished');
            $debugPayload = json_encode([
                'filetime' => $this->formatDate($game->fileTime),
                'start' => $this->formatDate($game->start),
                'end' => $this->formatDate($game->end),
                'importTime' => $this->formatDate($game->importTime),
                'now' => date('c', $now),
            ]);
            $output?->writeln(
                $debugPayload === false ? '' : $debugPayload,
                OutputInterface::VERBOSITY_VERBOSE
            );

            if ($isUpdated && $isStarted) {
                $logger->debug('Game is started');
                $output?->writeln('Game is started');
                return ResultFileImportResult::unfinished($game, 'game-started');
            }

            if ($isUpdated) {
                $logger->debug('Game is loaded');
                $output?->writeln('Game is loaded');
                return ResultFileImportResult::unfinished($game, 'game-loaded');
            }

            return ResultFileImportResult::skipped($game);
        }

        $null = true;
        foreach ($game->players as $player) {
            if ($player->score !== 0 || $player->shots !== 0) {
                $null = false;
                break;
            }
        }
        if ($null) {
            $logger->warning('Game is empty');
            $output?->writeln('Game is empty');
            return ResultFileImportResult::empty($game);
        }

        $logger->debug(
            'Starting game save from import loop',
            ['file' => $file, 'system' => $system, 'code' => $game->code ?? null]
        );
        if (!$game->save()) {
            $logger->error('Failed saving game into DB. ' . $file);
            $output?->writeln(
                Colors::color(ForegroundColors::RED) .
                'Failed saving game into DB' .
                Colors::reset()
            );
            return ResultFileImportResult::saveFailed($game);
        }
        $logger->debug(
            'Finished game save from import loop',
            ['file' => $file, 'system' => $system, 'code' => $game->code ?? null]
        );

        $this->clearImportedGameState($game, $system);

        $gameModel = GameFactory::getById($game->id ?? 0, ['system' => $system]);
        return ResultFileImportResult::imported($gameModel ?? $game);
    }

    private function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format('c');
    }

    /**
     * @template G of Game
     * @param G $game
     */
    public function clearImportedGameState(Game $game, string $system): void
    {
        $game::clearModelCache();
        if ($game->start !== null) {
            $this->cache->clean([$this->cache::Tags => ['games/' . $game->start->format('Y-m-d')]]);
        }

        $startedGame = Info::get($system . '-game-started');
        if ($startedGame instanceof Game && $game->resultsFile === $startedGame->resultsFile) {
            try {
                Info::set($system . '-game-started', null);
            } catch (\Dibi\Exception) {
            }
        }
    }
}
