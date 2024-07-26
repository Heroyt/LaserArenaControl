<?php

namespace Tests\Unit;

use App\Models\DataObjects\FairTeamDto;
use App\Models\DataObjects\PlayerSkillDto;
use App\Services\FairTeams;
use Codeception\Attribute\DataProvider;
use Codeception\Attribute\Depends;
use Codeception\Test\Unit;
use Generator;
use Tests\Support\UnitTester;

class FairTeamsTest extends Unit
{
    protected UnitTester $tester;

    public function provideTeamDeltas(): Generator {
        yield [
            'teams' => [],
            'expectedDeltas' => [],
        ];
        yield [
            'teams' => [new FairTeamDto(0, skill: 0)],
            'expectedDeltas' => [],
        ];
        yield [
          'teams' => [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 0),
          ],
           'expectedDeltas' => [
             '0-1' => 0,
           ]
        ];
        yield [
          'teams' => [
              new FairTeamDto(0, skill: 10),
              new FairTeamDto(1, skill: 0),
          ],
           'expectedDeltas' => [
             '0-1' => 10,
           ]
        ];
        yield [
          'teams' => [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 10),
          ],
           'expectedDeltas' => [
             '0-1' => 10,
           ]
        ];
        yield [
          'teams' => [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 10),
              new FairTeamDto(2, skill: 20),
          ],
           'expectedDeltas' => [
             '0-1' => 10,
             '0-2' => 20,
             '1-2' => 10,
           ]
        ];
        yield [
          'teams' => [
              new FairTeamDto(1, skill: 10),
              new FairTeamDto(2, skill: 20),
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(3, skill: 50),
          ],
           'expectedDeltas' => [
             '0-1' => 10,
             '0-2' => 20,
             '0-3' => 50,
             '1-2' => 10,
             '1-3' => 40,
             '2-3' => 30,
           ]
        ];
    }

    public function provideTeamMaxDelta(): Generator {
        yield [
          [],
          0,
        ];
        yield [
          [
            new FairTeamDto(0, skill: 10),
          ],
          0,
        ];
        yield [
          [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 0),
          ],
          0,
        ];
        yield [
            [
              new FairTeamDto(0, skill: 10),
              new FairTeamDto(1, skill: 0),
            ],
            10,
        ];
        yield [
            [
                new FairTeamDto(0, skill: 0),
                new FairTeamDto(1, skill: 10),
            ],
            10
        ];
        yield [
            [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 10),
              new FairTeamDto(2, skill: 20),
            ],
            20,
        ];
        yield [
            [
              new FairTeamDto(1, skill: 10),
              new FairTeamDto(2, skill: 20),
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(3, skill: 50),
            ],
            50,
        ];
    }
    public function provideTeamAvgDelta(): Generator {
        yield [
          [],
          0.0,
        ];
        yield [
          [
            new FairTeamDto(0, skill: 10),
          ],
          0.0,
        ];
        yield [
          [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 0),
          ],
          0.0,
        ];
        yield [
            [
              new FairTeamDto(0, skill: 10),
              new FairTeamDto(1, skill: 0),
            ],
            10.0,
        ];
        yield [
            [
                new FairTeamDto(0, skill: 0),
                new FairTeamDto(1, skill: 10),
            ],
            10.0
        ];
        yield [
            [
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(1, skill: 10),
              new FairTeamDto(2, skill: 20),
            ],
            40 / 3,
        ];
        yield [
            [
              new FairTeamDto(1, skill: 10),
              new FairTeamDto(2, skill: 20),
              new FairTeamDto(0, skill: 0),
              new FairTeamDto(3, skill: 50),
            ],
            160 / 6,
        ];
    }

    public function provideSplitPlayers(): Generator {
        $iterations = 10;
        $players = [
          new PlayerSkillDto('Tomážeg', 811),
          new PlayerSkillDto('Honza_24', 642),
          new PlayerSkillDto('Kirbouš', 640),
          new PlayerSkillDto('Jeff', 619),
          new PlayerSkillDto('Fat george', 551),
          new PlayerSkillDto('DavidJ', 548),
          new PlayerSkillDto('Teniska', 537),
          new PlayerSkillDto('Jižík', 537),
          new PlayerSkillDto('Staja', 462),
          new PlayerSkillDto('Náhradník', 453),
          new PlayerSkillDto('Saruše', 436),
          new PlayerSkillDto('Hvězda', 418),
          new PlayerSkillDto('Dave', 407),
          new PlayerSkillDto('Ma_ES', 388),
          new PlayerSkillDto('Růža', 388),
          new PlayerSkillDto('TIX', 372),
          new PlayerSkillDto('Šimky', 348),
          new PlayerSkillDto('Klubík', 327),
          new PlayerSkillDto('MatějHaHaHa', 323),
          new PlayerSkillDto('Mendonca', 250),
          new PlayerSkillDto('Mařena', 238),
          new PlayerSkillDto('Lucy', 220),
          new PlayerSkillDto('Tobík', 200),
          new PlayerSkillDto('Kikina', 180),
          new PlayerSkillDto('Hrad', 146),
          new PlayerSkillDto('Andrea', 142),
          new PlayerSkillDto('Adriana', 120),
          new PlayerSkillDto('Yidi', 76),
          new PlayerSkillDto('Monča', 68),
          new PlayerSkillDto('Senix', 49),
          new PlayerSkillDto('Rukybazuky', 24),
        ];
        for ($i = 0; $i < $iterations; $i++) {
            yield [
            $players,
            (int) ceil(count($players) / 5),
            500,
            40,
            60,
            30
            ];
        }
        for ($i = 0; $i < $iterations; $i++) {
            yield [
            $players,
            (int) ceil(count($players) / 4),
            500,
            40,
            55,
            25
            ];
        }
        for ($i = 0; $i < $iterations; $i++) {
            yield [
            $players,
            (int) ceil(count($players) / 4),
            500,
            10,
            55,
            25
            ];
        }
        for ($i = 0; $i < $iterations; $i++) {
            yield [
            $players,
            (int) ceil(count($players) / 4),
            0,
            0,
            55,
            25
            ];
        }
    }

    // tests

    #[DataProvider('provideTeamDeltas')]
    public function testCalculateDeltas(array $teams, array $expectedDeltas): void {
        $fairTeams = new FairTeams();

        $deltas = $fairTeams->calculateDeltas($teams);
        self::assertCount(count($teams) * (count($teams) - 1) / 2, $deltas);
        self::assertEquals($expectedDeltas, $deltas);
    }

    #[Depends('testCalculateDeltas')]
    #[DataProvider('provideTeamMaxDelta')]
    public function testMaxDelta(array $teams, int $expectedMax): void {
        $fairTeams = new FairTeams();

        self::assertSame($expectedMax, $fairTeams->getMaxSkillDelta($teams));
    }

    #[Depends('testCalculateDeltas')]
    #[DataProvider('provideTeamAvgDelta')]
    public function testAvgDelta(array $teams, float $expectedAvg): void {
        $fairTeams = new FairTeams();

        self::assertSame($expectedAvg, $fairTeams->getAvgSkillDelta($teams));
    }

    #[Depends('testCalculateDeltas', 'testMaxDelta')]
    #[DataProvider('provideSplitPlayers')]
    public function testSplitPlayers(array $players, int $teamCount, int $iterations, int $maxIterationsWithoutImprovement, int $maxDelta, int $maxAvgDelta): void {
        $fairTeams = new FairTeams($iterations, $maxIterationsWithoutImprovement);
        $teams = $fairTeams->splitPlayers($players, $teamCount);
        self::assertCount($teamCount, $teams);

        $delta = $fairTeams->getMaxSkillDelta($teams);
        self::assertLessThan($maxDelta, $delta);

        $delta = $fairTeams->getAvgSkillDelta($teams);
        self::assertLessThan($maxAvgDelta, $delta);
    }
}
