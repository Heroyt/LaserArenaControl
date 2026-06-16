<?php

namespace Tests\Unit;

use App\DataObjects\Import\ResultFileImportResult;
use App\GameModels\Game\Game;
use App\Services\ResultFileImporter;
use DateTimeImmutable;
use Lsr\Caching\Cache;
use Lsr\Logging\Logger;
use PHPUnit\Framework\TestCase;

class ResultFileImporterTest extends TestCase
{
    public function testStartedGameUsesStartedWindowInsteadOfLoadedWindow(): void {
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

    public function testFreshNotStartedGameIsLoaded(): void {
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

    public function testOldNotStartedGameIsSkipped(): void {
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

    public function testOldStartedGameBeyondStartedWindowIsSkipped(): void {
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

    private function createGame(bool $isStarted): Game {
        $game = new class ($isStarted) extends Game {
            public function __construct(private bool $started) {
            }

            public function isFinished(): bool {
                return false;
            }

            public function isStarted(): bool {
                return $this->started;
            }
        };

        return $game;
    }
}
