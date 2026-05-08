<?php

namespace Tests\Unit;

use App\Services\EventService;
use App\Services\FeatureConfig;
use App\Services\LaserLiga\LigaApi;
use App\Services\ResultFileImportFinalizer;
use Lsr\Logging\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ResultFileImportFinalizerTest extends TestCase
{
    public function testTriggerImportedSkipsEmptyCount(): void
    {
        $eventService = $this->createEventServiceMock();
        $eventService
            ->expects($this->never())
            ->method('trigger');

        $this->createFinalizer($eventService)->triggerImported(0);
    }

    /**
     * @return EventService&MockObject
     */
    private function createEventServiceMock(): EventService
    {
        return $this
            ->getMockBuilder(EventService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['trigger'])
            ->getMock();
    }

    private function createFinalizer(?EventService $eventService = null): ResultFileImportFinalizer
    {
        /** @var LigaApi&Stub $ligaApi */
        $ligaApi = $this->createStub(LigaApi::class);

        /** @var FeatureConfig&Stub $featureConfig */
        $featureConfig = $this->createStub(FeatureConfig::class);

        return new ResultFileImportFinalizer(
            $eventService ?? $this->createStub(EventService::class),
            $ligaApi,
            $featureConfig,
        );
    }

    public function testTriggerImportedDispatchesEvent(): void
    {
        $eventService = $this->createEventServiceMock();
        $eventService
            ->expects($this->once())
            ->method('trigger')
            ->with('game-imported', ['count' => 2])
            ->willReturn(true);

        $this->createFinalizer($eventService)->triggerImported(2);
    }

    public function testFinalizeEmptyGameListOnlyLogs(): void
    {
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
