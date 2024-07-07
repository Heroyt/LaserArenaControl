<?php

namespace Tests\Unit;

use App\Helpers\Gender;
use App\Services\GenderService;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Tests\Support\UnitTester;

class GenderServiceTest extends Unit
{
    protected UnitTester $tester;

    #[DataProvider('wordProvider')]
    public function testRanking(string $value, Gender $expected): void {
        $this->assertEquals(
            $expected,
            GenderService::rankWord($value),
            'Invalid gender for "' . $value . '"'
        );
    }

    /**
     * @return array{value:string,expected:Gender}[]
     */
    protected function wordProvider(): array {
        return [
          ['value' => 'Heroyt', 'expected' => Gender::MALE],
          ['value' => 'Tomáš', 'expected' => Gender::MALE],
          ['value' => 'David', 'expected' => Gender::MALE],
          ['value' => 'guy', 'expected' => Gender::MALE],
          ['value' => 'boy', 'expected' => Gender::MALE],
          ['value' => 'Davýdeg', 'expected' => Gender::MALE],
          ['value' => 'Sofčilka', 'expected' => Gender::FEMALE],
          ['value' => 'girl', 'expected' => Gender::FEMALE],
          ['value' => 'Jana', 'expected' => Gender::FEMALE],
          ['value' => 'veverka', 'expected' => Gender::FEMALE],
          ['value' => 'stůl', 'expected' => Gender::MALE],
          ['value' => 'opice', 'expected' => Gender::FEMALE],
          ['value' => 'pes', 'expected' => Gender::MALE],
          ['value' => 'pan veverka', 'expected' => Gender::MALE],
          ['value' => 'paní pes', 'expected' => Gender::FEMALE],
        ];
    }
}
