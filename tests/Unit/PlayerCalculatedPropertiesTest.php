<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\GameModels\Game\PlayerCalculatedProperties;
use Codeception\Test\Unit;

class PlayerCalculatedPropertiesTest extends Unit
{
    public function test_relative_hits_returns_null_for_zero_expected_hits(): void {
        $player = new class {
            use PlayerCalculatedProperties;

            public int $hits = 10;
            public int $deaths = 4;

            public function getExpectedAverageHitCount(): float {
                return 0.0;
            }

            public function getExpectedAverageDeathCount(): float {
                return 10.0;
            }
        };

        $this::assertNull($player->relativeHits);
    }

    public function test_relative_deaths_returns_null_for_zero_expected_deaths(): void {
        $player = new class {
            use PlayerCalculatedProperties;

            public int $hits = 10;
            public int $deaths = 4;

            public function getExpectedAverageHitCount(): float {
                return 10.0;
            }

            public function getExpectedAverageDeathCount(): float {
                return 0.0;
            }
        };

        $this::assertNull($player->relativeDeaths);
    }
}
