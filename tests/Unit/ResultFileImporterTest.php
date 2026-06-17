<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DataObjects\Import\ResultFileImportResult;
use App\GameModels\Game\Game;
use App\GameModels\Game\Lasermaxx\Evo6\Player;
use App\GameModels\Game\Lasermaxx\Evo6\Team;
use App\Services\ResultFileImporter;
use DateTimeImmutable;
use Dibi\Row;
use Lsr\Caching\Cache;
use Lsr\LaserLiga\PlayerProviderInterface;
use Lsr\Lg\Results\AbstractResultsParser;
use Lsr\Lg\Results\Interface\GameModeProviderInterface;
use Lsr\Lg\Results\Interface\Models\GameInterface as ParsedGameInterface;
use Lsr\Logging\Logger;
use PHPUnit\Framework\TestCase;

class ResultFileImporterTest extends TestCase
{
    public function test_parse_content_preserves_inline_source_metadata(): void {
        $file = '/var/results/evo6/0007.game';
        $mtime = 1_700_000_123;
        $importer = new ResultFileImporter($this->createStub(Cache::class));
        $parser = new class (
            $this->createStub(PlayerProviderInterface::class),
            $this->createStub(GameModeProviderInterface::class),
        ) extends AbstractResultsParser {
            public function __construct(
                PlayerProviderInterface $playerProvider,
                GameModeProviderInterface $gameModeProvider,
            ) {
                parent::__construct($playerProvider, $gameModeProvider, Game::class);
            }

            public static function getFileGlob(): string {
                return '*.game';
            }

            public static function checkFile(string $fileName = '', string $contents = ''): bool {
                return true;
            }

            /**
             * @return Game<Team, Player>
             */
            public function parse(): Game {
                $game = new class extends \App\GameModels\Game\Lasermaxx\Evo6\Game {
                    public function __construct(?int $id = null, ?Row $dbRow = null) {
                        unset($id, $dbRow);
                    }

                    public function isFinished(): bool {
                        return false;
                    }
                };
                $game->resultsFile = $this->getSourcePath();
                $game->modeName = $this->getSourceBaseName();
                $sourceMtime = $this->getSourceMtime();
                $game->fileTime = $sourceMtime === null ? null : new DateTimeImmutable('@' . $sourceMtime);

                return $game;
            }

            /**
             * @param ParsedGameInterface<Team, Player, array<string, mixed>> $game
             * @param array<string, mixed> $meta
             */
            protected function processExtensions(ParsedGameInterface $game, array $meta): void {
            }
        };

        $game = $importer->parseContent(
            $parser,
            'evo6',
            $file,
            "content\n",
            $mtime,
            $this->createStub(Logger::class),
        );

        $this->assertSame($file, $parser->getSourcePath());
        $this->assertSame('0007', $parser->getSourceBaseName());
        $this->assertSame($mtime, $parser->getSourceMtime());
        $this->assertSame($file, $game->resultsFile);
        $this->assertSame('0007', $game->modeName);
        $this->assertSame($mtime, $game->fileTime?->getTimestamp());
    }

    public function test_started_game_uses_started_window_instead_of_loaded_window(): void {
        $result = $this->importUnfinishedGame(
            isStarted: true,
            fileTime: new DateTimeImmutable('@600'),
            start: new DateTimeImmutable('@700'),
            now: 1000,
            gameLoadedTime: 300,
            gameStartedTime: 400,
        );

        $this->assertSame('game-started', $result->unfinishedEvent);
    }

    public function test_fresh_not_started_game_is_loaded(): void {
        $result = $this->importUnfinishedGame(
            isStarted: false,
            fileTime: new DateTimeImmutable('@900'),
            start: null,
            now: 1000,
            gameLoadedTime: 300,
            gameStartedTime: 400,
        );

        $this->assertSame('game-loaded', $result->unfinishedEvent);
    }

    public function test_old_not_started_game_is_skipped(): void {
        $result = $this->importUnfinishedGame(
            isStarted: false,
            fileTime: new DateTimeImmutable('@600'),
            start: null,
            now: 1000,
            gameLoadedTime: 300,
            gameStartedTime: 400,
        );

        $this->assertSame('', $result->unfinishedEvent);
        $this->assertNull($result->unfinishedGame);
    }

    public function test_old_started_game_beyond_started_window_is_skipped(): void {
        $result = $this->importUnfinishedGame(
            isStarted: true,
            fileTime: new DateTimeImmutable('@600'),
            start: new DateTimeImmutable('@500'),
            now: 1000,
            gameLoadedTime: 300,
            gameStartedTime: 400,
        );

        $this->assertSame('', $result->unfinishedEvent);
        $this->assertNull($result->unfinishedGame);
    }

    private function importUnfinishedGame(
        bool               $isStarted,
        ?DateTimeImmutable $fileTime,
        ?DateTimeImmutable $start,
        int                $now,
        int                $gameLoadedTime,
        int                $gameStartedTime,
    ): ResultFileImportResult {
        $game = $this->createGame($isStarted);
        $game->fileTime = $fileTime;
        $game->start = $start;

        $importer = new ResultFileImporter($this->createStub(Cache::class));

        return $importer->importParsed(
            $game,
            'evo6',
            '/tmp/results/0001.game',
            $now,
            $gameLoadedTime,
            $gameStartedTime,
            $this->createStub(Logger::class),
        );
    }

    /**
     * @return Game<Team, Player>
     */
    private function createGame(bool $isStarted): Game {
        $game = new class extends \App\GameModels\Game\Lasermaxx\Evo6\Game {
            public bool $started = false;

            public function __construct(?int $id = null, ?Row $dbRow = null) {
                unset($id, $dbRow);
            }

            public function isFinished(): bool {
                return false;
            }

            public function isStarted(): bool {
                return $this->started;
            }
        };
        $game->started = $isStarted;

        return $game;
    }
}
