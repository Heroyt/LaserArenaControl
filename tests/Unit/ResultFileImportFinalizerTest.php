<?php

namespace Tests\Unit;

use App\GameModels\Game\Lasermaxx\Evo6\Game;
use App\Services\EventService;
use App\Services\FeatureConfig;
use App\Services\GameStateStorage;
use App\Services\LaserLiga\LigaApi;
use App\Services\ResultFileImportFinalizer;
use DateTimeImmutable;
use Lsr\Logging\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ResultFileImportFinalizerTest extends TestCase
{
    public function test_trigger_imported_skips_empty_count(): void {
        $eventService = $this->createEventServiceMock();
        $eventService
            ->expects($this->never())
            ->method('trigger');

        $this->createFinalizer($eventService)->triggerImported(0);
    }

    /**
     * @return EventService&MockObject
     */
    private function createEventServiceMock(): EventService {
        return $this
            ->getMockBuilder(EventService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trigger'])
            ->getMock();
    }

    private function createFinalizer(
        ?EventService     $eventService = null,
        ?GameStateStorage $gameStateStorage = null,
    ): ResultFileImportFinalizer {
        /** @var LigaApi&Stub $ligaApi */
        $ligaApi = $this->createStub(LigaApi::class);

        /** @var FeatureConfig&Stub $featureConfig */
        $featureConfig = $this->createStub(FeatureConfig::class);

        return new ResultFileImportFinalizer(
            $eventService ?? $this->createStub(EventService::class),
            $ligaApi,
            $featureConfig,
            $gameStateStorage ?? $this->createStub(GameStateStorage::class),
        );
    }

    public function test_trigger_imported_dispatches_event(): void {
        $eventService = $this->createEventServiceMock();
        $eventService
            ->expects($this->once())
            ->method('trigger')
            ->with('game-imported', ['count' => 2])
            ->willReturn(true);

        $this->createFinalizer($eventService)->triggerImported(2);
    }

    public function test_trigger_unfinished_stores_game_and_dispatches_event(): void {
        /** @var Game $game */
        $game = $this->createStub(Game::class);
        $game->resultsFile = '0001';
        $game->fileTime = new DateTimeImmutable('@123');

        $eventService = $this->createEventServiceMock();
        $eventService
            ->expects($this->once())
            ->method('trigger')
            ->with('game-loaded', ['game' => '0001'])
            ->willReturn(true);

        $gameStateStorage = $this
            ->getMockBuilder(GameStateStorage::class)
            ->onlyMethods(['get', 'set'])
            ->getMock();
        $gameStateStorage
            ->expects($this->once())
            ->method('get')
            ->with('evo6-game-loaded')
            ->willReturn(null);
        $gameStateStorage
            ->expects($this->once())
            ->method('set')
            ->with('evo6-game-loaded', $game);

        $this
            ->createFinalizer($eventService, $gameStateStorage)
            ->triggerUnfinished($game, 'game-loaded', $this->createStub(Logger::class));
    }

    public function test_finalize_empty_game_list_only_logs(): void {
        $logger = $this
            ->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['info'])
            ->getMock();
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('No games to synchronize to public');

        $this->createFinalizer()->finalize([], $logger);
    }
}
