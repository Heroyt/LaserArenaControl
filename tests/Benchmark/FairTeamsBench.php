<?php

namespace Tests\Benchmark;

use App\DataObjects\PlayerSkillDto;
use App\Services\FairTeams;
use Generator;
use PhpBench\Attributes as Bench;
use Random\RandomException;

/**
 *
 */
class FairTeamsBench
{
    public function provideFairTeams(): Generator {
        yield 'players-31' => [
          'players' => [
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
          ]
        ];
        yield 'players-14' => [
          'players' => [
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
            ]
        ];
    }

    public function provideService(): Generator {
        yield 'default-default' => ['service' => []];
        yield '100-default' => ['service' => [100]];
        yield '500-10' => ['service' => [500, 10]];
        yield '1000-100' => ['service' => [500, 10]];
        yield 'no-annealing' => ['service' => [0, 0]];
    }

    public function provideTeamSize(): Generator {
        yield '3' => ['teamSize' => 3];
        yield '4' => ['teamSize' => 4];
        yield '5' => ['teamSize' => 5];
        yield '6' => ['teamSize' => 6];
    }

    /**
     * @param  array{service: int[], players: PlayerSkillDto[], teamSize: int}  $parameters
     * @return int
     * @throws RandomException
     */
    #[Bench\Revs(10), Bench\Iterations(10), Bench\ParamProviders(['provideService', 'provideFairTeams', 'provideTeamSize'])]
    public function benchFairTeams(array $parameters): int {
        $service = new FairTeams(...$parameters['service']);
        $teams = $service->splitPlayers(
            $parameters['players'],
            (int) ceil(count($parameters['players']) / $parameters['teamSize'])
        );
        return $service->getMaxSkillDelta($teams);
    }
}
