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
use Lsr\Exceptions\FileException;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Logging\Logger;
use ReflectionProperty;
use RuntimeException;
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
        $game = $this->parse($parser, $system, $file, $logger);

        return $this->importParsed($game, $system, $file, $now, $gameLoadedTime, $logger, $output);
    }

    /** @phpstan-ignore-next-line missingType.generics */
    public function parse(
        AbstractResultsParser $parser,
        string                $system,
        string                $file,
        Logger                $logger,
    ): Game
    {
        $logger->debug('Preparing parser for file', ['file' => $file, 'system' => $system]);
        $parser->setFile($file);
        $logger->debug('Starting parser->parse()', ['file' => $file, 'system' => $system]);
        $game = $parser->parse();
        if (!$game instanceof Game) {
            throw new RuntimeException('Parsed result is not an application game model.');
        }
        $logger->debug(
            'Finished parser->parse()',
            [
                'file' => $file,
                'system' => $system,
                'code' => $game->code,
                'finished' => $game->isFinished(),
            ]
        );

        return $game;
    }

    /** @phpstan-ignore-next-line missingType.generics */
    public function parseContent(
        AbstractResultsParser $parser,
        string                $system,
        string                $file,
        string                $content,
        int                   $mtime,
        Logger                $logger,
    ): Game
    {
        $logger->debug('Preparing parser for inline file content', ['file' => $file, 'system' => $system]);
        $tempFile = $this->createInlineSourceFile($file, $content, $mtime);

        try {
            $parser->setContents(mb_convert_encoding($content, 'UTF-8'));
            $this->setParserFileName($parser, $tempFile);
            $logger->debug('Starting parser->parse() from inline content', ['file' => $file, 'system' => $system]);
            $game = $parser->parse();
        } finally {
            if (is_file($tempFile) && !unlink($tempFile)) {
                $logger->warning('Failed to remove inline result import temp file.', ['file' => $tempFile]);
            }
            $tempDir = dirname($tempFile);
            $tempDirFiles = is_dir($tempDir) ? scandir($tempDir) : false;
            if ($tempDirFiles === ['.', '..']) {
                rmdir($tempDir);
            }
        }

        if (!$game instanceof Game) {
            throw new RuntimeException('Parsed result is not an application game model.');
        }
        if ($game->resultsFile === pathinfo($tempFile, PATHINFO_FILENAME)) {
            $game->resultsFile = pathinfo($file, PATHINFO_FILENAME);
        }
        $logger->debug(
            'Finished parser->parse() from inline content',
            [
                'file' => $file,
                'system' => $system,
                'code' => $game->code,
                'finished' => $game->isFinished(),
            ]
        );

        return $game;
    }

    /**
     * @template G of Game
     * @param G $game
     */
    public function importParsed(
        Game             $game,
        string           $system,
        string           $file,
        int              $now,
        int              $gameLoadedTime,
        Logger           $logger,
        ?OutputInterface $output = null,
    ): ResultFileImportResult
    {
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

        $this->clearImportedGameState($game, $system, $logger);

        $gameModel = GameFactory::getById($game->id ?? 0, ['system' => $system]);
        return ResultFileImportResult::imported($gameModel ?? $game);
    }

    private function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format('c');
    }

    private function createInlineSourceFile(string $file, string $content, int $mtime): string
    {
        $dir = TMP_DIR . 'result-import-inline/' . sha1($file . ':' . $mtime . ':' . hash('sha256', $content)) . '/';
        if (!is_dir($dir) && !mkdir($dir, recursive: true) && !is_dir($dir)) {
            throw new RuntimeException('Failed to create inline result import directory.');
        }

        $basename = basename($file);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            $basename = 'result.game';
        }

        $tempFile = $dir . $basename;
        if (file_put_contents($tempFile, $content) === false) {
            throw new FileException('Failed to write inline result import file.');
        }
        touch($tempFile, $mtime);

        return $tempFile;
    }

    /** @phpstan-ignore missingType.generics */
    private function setParserFileName(AbstractResultsParser $parser, string $file): void
    {
        new ReflectionProperty(AbstractResultsParser::class, 'fileName')->setValue($parser, $file);
    }

    /**
     * @template G of Game
     * @param G $game
     */
    public function clearImportedGameState(Game $game, string $system, ?Logger $logger = null): void
    {
        $game::clearModelCache();
        if ($game->start !== null) {
            $this->cache->clean([$this->cache::Tags => ['games/' . $game->start->format('Y-m-d')]]);
        }

        $startedGame = Info::get($system . '-game-started');
        if ($startedGame instanceof Game && $game->resultsFile === $startedGame->resultsFile) {
            try {
                Info::set($system . '-game-started', null);
            } catch (\Dibi\Exception $e) {
                $logger?->error(
                    'Failed to clear started game state after import',
                    ['key' => $system . '-game-started', 'exception' => $e->getMessage()]
                );
            }
        }
    }
}
