<?php

namespace Tests\Unit;

use App\DataObjects\Import\ResultGameStatusState;
use App\Tools\ResultParsing\Evo5\ResultsParser as Evo5ResultsParser;
use App\Tools\ResultParsing\Evo6\ResultsParser as Evo6ResultsParser;
use PHPUnit\Framework\TestCase;

class ResultGameStatusReaderTest extends TestCase
{
    public function test_evo6_loaded_status_extraction(): void {
        $status = Evo6ResultsParser::readStatus(
            '0012.game',
            $this->content('EVO-6 MAXX', 'GAME{12,,20250208214146,20000101000000,3}#'),
        );

        $this->assertSame(ResultGameStatusState::LOADED, $status->state);
        $this->assertSame('evo6', $status->system);
        $this->assertSame(12, $status->fileNumber);
        $this->assertSame(3, $status->playerCount);
        $this->assertSame('20250208214146', $status->loadedAt?->format('YmdHis'));
        $this->assertNull($status->playStartedAt);
        $this->assertNull($status->playEndedAt);
        $this->assertNull($status->realEndedAt);
    }

    public function test_evo6_started_status_extraction(): void {
        $status = Evo6ResultsParser::readStatus(
            '0012.game',
            $this->content(
                'EVO-6 MAXX',
                'GAME{12,,20250208214146,20000101000000,3}#',
                'TIMING{10, 15, 5, 20250208214156,20000101000000,20000101000000}#',
            ),
        );

        $this->assertSame(ResultGameStatusState::STARTED, $status->state);
        $this->assertSame('20250208214156', $status->playStartedAt?->format('YmdHis'));
        $this->assertNull($status->playEndedAt);
        $this->assertNull($status->realEndedAt);
    }

    public function test_evo6_finished_status_extraction_with_six_timing_arguments(): void {
        $status = Evo6ResultsParser::readStatus(
            '0012.game',
            $this->content(
                'EVO-6 MAXX',
                'GAME{12,,20250208214146,20250208214447,3}#',
                'TIMING{10, 15, 5, 20250208214156,20250208214442,20250208214447}#',
            ),
        );

        $this->assertSame(ResultGameStatusState::FINISHED, $status->state);
        $this->assertSame('20250208214442', $status->playEndedAt?->format('YmdHis'));
        $this->assertSame('20250208214447', $status->realEndedAt?->format('YmdHis'));
        $this->assertSame('20250208214447', $status->importedAt?->format('YmdHis'));
    }

    public function test_evo6_finished_status_extraction_with_five_timing_arguments(): void {
        $status = Evo6ResultsParser::readStatus(
            '0012.game',
            $this->content(
                'EVO-6 MAXX',
                'GAME{12,,20250208214146,20250208214447,3}#',
                'TIMING{10, 15, 5, 20250208214156,20250208214442}#',
            ),
        );

        $this->assertSame(ResultGameStatusState::FINISHED, $status->state);
        $this->assertSame('20250208214442', $status->playEndedAt?->format('YmdHis'));
        $this->assertNull($status->realEndedAt);
    }

    public function test_evo5_status_extraction(): void {
        $status = Evo5ResultsParser::readStatus(
            '0012.game',
            $this->content(
                'EVO-5 MAXX',
                'GAME{12,,20250208214146,20250208214447,3}#',
                'TIMING{10, 15, 5, 20250208214156,20250208214442,20250208214447}#',
            ),
        );

        $this->assertSame(ResultGameStatusState::FINISHED, $status->state);
        $this->assertSame('evo5', $status->system);
        $this->assertSame(12, $status->fileNumber);
        $this->assertSame(3, $status->playerCount);
    }

    public function test_unknown_status_for_wrong_system(): void {
        $status = Evo6ResultsParser::readStatus(
            '0012.game',
            $this->content('EVO-5 MAXX', 'GAME{12,,20250208214146,20250208214447,3}#'),
        );

        $this->assertSame(ResultGameStatusState::UNKNOWN, $status->state);
    }

    private function content(string $site, string ...$lines): string {
        return implode("\r\n", [
            'SITE{Arena,1,' . $site . '}#',
            ...$lines,
        ]);
    }
}
