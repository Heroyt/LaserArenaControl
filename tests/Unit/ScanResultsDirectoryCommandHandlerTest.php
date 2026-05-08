<?php

namespace Tests\Unit;

use App\CQRS\CommandHandlers\ScanResultsDirectoryCommandHandler;
use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\DataObjects\Import\QueuedResultFileImport;
use App\DataObjects\Import\ResultsScanResult;
use App\Services\ResultsDirectoryScanner;
use Lsr\CQRS\CommandBus;
use PHPUnit\Framework\TestCase;

class ScanResultsDirectoryCommandHandlerTest extends TestCase
{
    public function testCommandUsesScanHandler(): void
    {
        $command = new ScanResultsDirectoryCommand('/tmp/results');

        $this->assertSame(ScanResultsDirectoryCommandHandler::class, $command->getHandler());
    }

    public function testHandlerDelegatesToScanner(): void
    {
        $queuedFile = new QueuedResultFileImport(
            '/tmp/results/0001.game',
            sha1('/tmp/results/0001.game'),
            'evo6',
            123,
            456,
            str_repeat('a', 64),
            sha1('/tmp/results/0001.game:123:456:' . str_repeat('a', 64)),
        );
        $result = new ResultsScanResult('/tmp/results/', 1, 1, 0, 0, [$queuedFile]);
        $scanner = $this
            ->getMockBuilder(ResultsDirectoryScanner::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['scan'])
            ->getMock();
        $commandBus = $this
            ->getMockBuilder(CommandBus::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatchAsync'])
            ->getMock();
        $scanner
            ->expects($this->once())
            ->method('scan')
            ->with('/tmp/results', true, 5)
            ->willReturn($result);
        $commandBus
            ->expects($this->once())
            ->method('dispatchAsync');

        $handler = new ScanResultsDirectoryCommandHandler($scanner, $commandBus);

        $this->assertSame(
            $result,
            $handler->handle(new ScanResultsDirectoryCommand('/tmp/results', all: true, limit: 5))
        );
    }
}
