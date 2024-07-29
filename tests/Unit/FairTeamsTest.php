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
        $teamSizes = [5,4,3,2];
        $iterationValues = [2000, 1000, 500, 100, 50, 0];
        $iterationWithoutImprovementValues = [500, 100, 50, 20, 10, 0];
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

        foreach ($teamSizes as $teamSize) {
            foreach ($iterationValues as $iterationValue) {
                foreach ($iterationWithoutImprovementValues as $iterationWithoutImprovementValue) {
                    if ($iterationValue < $iterationWithoutImprovementValue) {
                        continue;
                    }
                    for ($i = 0; $i < $iterations; $i++) {
                        yield [
                          $i,
                          $players,
                          (int) ceil(count($players) / $teamSize),
                          $iterationValue,
                          $iterationWithoutImprovementValue,
                        ];
                    }
                }
            }
        }

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
        ];

        foreach ($teamSizes as $teamSize) {
            foreach ($iterationValues as $iterationValue) {
                foreach ($iterationWithoutImprovementValues as $iterationWithoutImprovementValue) {
                    if ($iterationValue < $iterationWithoutImprovementValue) {
                        continue;
                    }
                    for ($i = 0; $i < $iterations; $i++) {
                        yield [
                          $i,
                          $players,
                          (int) ceil(count($players) / $teamSize),
                          $iterationValue,
                          $iterationWithoutImprovementValue,
                        ];
                    }
                }
            }
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
    public function testSplitPlayers(int $iteration, array $players, int $teamCount, int $iterations, int $maxIterationsWithoutImprovement): void {
        $fairTeams = new FairTeams($iterations, $maxIterationsWithoutImprovement);
        $teams = $fairTeams->splitPlayers($players, $teamCount);
        self::assertCount($teamCount, $teams);

        // Test player counts in teams
        $maxTeamCount = (int) ceil(count($players) / $teamCount);
        foreach ($teams as $team) {
            $playerCount = count($team->players);
            self::assertLessThanOrEqual($maxTeamCount, $playerCount);
            self::assertGreaterThanOrEqual($maxTeamCount - 1, $playerCount);
        }

        // Save stats
        $deltas = $fairTeams->calculateDeltas($teams);
        $out = number_format($iteration, 0, '.', '') . ',';
        $out .= number_format(count($players), 0, '.', '') . ',';
        $out .= number_format($teamCount, 0, '.', '') . ',';
        $out .= number_format($iterations, 0, '.', '') . ',';
        $out .= number_format($maxIterationsWithoutImprovement, 0, '.', '') . ',';
        $out .= number_format($fairTeams->getMinSkillDelta($teams, $deltas), 0, '.', '') . ',';
        $out .= number_format($fairTeams->getMaxSkillDelta($teams, $deltas), 0, '.', '') . ',';
        $out .= number_format($fairTeams->getMeanSkillDelta($teams, $deltas), 3) . ',';
        $out .= number_format($fairTeams->getAvgSkillDelta($teams, $deltas), 3, '.', '') . ',';
        $out .= number_format($fairTeams->getSkillDeltaStdDev($teams, $deltas), 4) . ',';
        $out .= number_format($fairTeams->getTotalSkillDelta($teams, $deltas), 0, '.', '') . PHP_EOL;
        file_put_contents('test.out.csv', $out, FILE_APPEND);
    }

    public function __construct(string $name) {
        file_put_contents('test.out.csv', 'Iteration,Player count,Team count,Iterations,MaxIterationsWI,Delta Min,Delta Max,Delta mean,Delta average,Delta stdDev,Delta total' . PHP_EOL);
        parent::__construct($name);
    }
}
