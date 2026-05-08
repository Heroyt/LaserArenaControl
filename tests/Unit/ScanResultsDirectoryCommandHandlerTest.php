<?php

namespace Tests\Unit;

use App\CQRS\CommandHandlers\ScanResultsDirectoryCommandHandler;
use App\CQRS\Commands\ScanResultsDirectoryCommand;
use App\DataObjects\Import\ResultsScanResult;
use App\Services\ResultsDirectoryScanner;
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
        $result = new ResultsScanResult('/tmp/results/', 1, 1, 0, 0);
        $scanner = $this
            ->getMockBuilder(ResultsDirectoryScanner::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['scan'])
            ->getMock();
        $scanner
            ->expects($this->once())
            ->method('scan')
            ->with('/tmp/results', true, 5)
            ->willReturn($result);

        $handler = new ScanResultsDirectoryCommandHandler($scanner);

        $this->assertSame(
            $result,
            $handler->handle(new ScanResultsDirectoryCommand('/tmp/results', all: true, limit: 5))
        );
    }
}
