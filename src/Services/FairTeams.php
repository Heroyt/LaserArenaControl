<?php

namespace App\Services;

use App\Models\DataObjects\FairTeamDto;
use App\Models\DataObjects\PlayerSkillDto;
use Random\RandomException;

/**
 *
 */
class FairTeams
{
    public function __construct(
      public readonly int $maxIterations = 1000,
      public readonly int $maxIterationsWithoutImprovement = 40,
    ) {}

    /**
     * @param  PlayerSkillDto[]  $players
     * @param  int  $teamCount
     * @return FairTeamDto[]
     * @throws RandomException
     */
    public function splitPlayers(array $players, int $teamCount) : array {
        assert($teamCount > 2, 'Cannot split players into less than 2 teams');

        $playerCount = count($players);
        if ($playerCount < $teamCount) {
            return [];
        }

        // Sort players by score
        usort($players, static fn($playerA, $playerB) => $playerB->skill - $playerA->skill);

        $teams = [];

        // Create teams
        for ($i = 0; $i < $teamCount; $i++) {
            $teams[] = new FairTeamDto($i + 1);
        }

        $scoreSum = array_reduce($players, static fn(int $carry, PlayerSkillDto $player) => $carry + $player->skill, 0);
        $teamIdealScore = $scoreSum / $teamCount;
        $maxPlayersInTeam = (int) ceil($playerCount / $teamCount);

        // Add players to teams - Try to fit the players in the best way possible
        foreach ($players as $player) {
            /** @var FairTeamDto $minSkillTeam Remember the team with minimum score and empty slots */
            $minSkillTeam = $teams[0];

            // Find the first team with the best fit
            $found = false;
            foreach ($teams as $team) {
                $teamPlayerCount = count($team->players);
                if ($teamPlayerCount === $maxPlayersInTeam) {
                    continue;
                }
                if ($minSkillTeam->skill > $team->skill) {
                    $minSkillTeam = $team;
                }
                $remainingSkill = $teamIdealScore - $team->skill;
                $remainingSpots = $maxPlayersInTeam - $teamPlayerCount;
                $remainingSkillPerPlayer = $remainingSkill / $remainingSpots;

                if ($player->skill <= $remainingSkillPerPlayer) {
                    $team->players[] = $player;
                    $team->skill += $player->skill;
                    $found = true;
                    break;
                }
            }
            if ($found) {
                continue;
            }

            // Add into team with empty spot and minimum total skill
            $minSkillTeam->skill += $player->skill;
            $minSkillTeam->players[] = $player;
        }

        // Run simulated annealing optimization to try to find some improvement
        $iterationsWithoutImprovement = 0;
        for ($it = 0; $it < $this->maxIterations && $iterationsWithoutImprovement < $this->maxIterationsWithoutImprovement; $it++) {
            [$t1, $t2] = array_rand($teams, 2);

            $score = abs($teams[$t1]->skill - $teamIdealScore) + abs($teams[$t2]->skill - $teamIdealScore);

            $p1 = array_rand($teams[$t1]->players);
            $player1 = $teams[$t1]->players[$p1];
            $p2 = array_rand($teams[$t2]->players);
            $player2 = $teams[$t2]->players[$p2];

            if ($player1->skill === $player2->skill) {
                if (random_int(1, 2) < 2) {
                    $teams[$t1]->players[$p1] = $player2;
                    $teams[$t2]->players[$p2] = $player1;
                }
                $iterationsWithoutImprovement++;
                continue;
            }

            $score1 = $teams[$t1]->skill - $player1->skill + $player2->skill;
            $score2 = $teams[$t2]->skill - $player2->skill + $player1->skill;
            $newScore = abs($score1 - $teamIdealScore) + abs($score2 - $teamIdealScore);

            if ($newScore < $score) {
                $teams[$t1]->players[$p1] = $player2;
                $teams[$t2]->players[$p2] = $player1;
                $teams[$t1]->skill = $score1;
                $teams[$t2]->skill = $score2;

                $iterationsWithoutImprovement = 0;
                continue;
            }

            $iterationsWithoutImprovement++;
        }

        return $teams;

    }

    /**
     * @param  FairTeamDto[]  $teams
     * @param  array<string,int>|null  $deltas
     * @return int
     */
    public function getMinSkillDelta(array $teams, ?array $deltas = null) : int {
        $deltas ??= $this->calculateDeltas($teams);
        if (count($deltas) === 0) {
            return 0;
        }
        return min($deltas);
    }

    /**
     * @param  FairTeamDto[]  $teams
     * @return array<string,int>
     */
    public function calculateDeltas(array $teams) : array {
        $deltas = [];
        foreach ($teams as $team) {
            foreach ($teams as $team2) {
                if ($team->key === $team2->key) {
                    continue;
                }
                $key = min($team->key, $team2->key).'-'.max($team->key, $team2->key);
                $deltas[$key] = abs($team->skill - $team2->skill);
            }
        }
        return $deltas;
    }

    /**
     * @param  FairTeamDto[]  $teams
     * @param  array<string,int>|null  $deltas
     * @return int
     */
    public function getMaxSkillDelta(array $teams, ?array $deltas = null) : int {
        $deltas ??= $this->calculateDeltas($teams);
        if (count($deltas) === 0) {
            return 0;
        }
        return max($deltas);
    }

    /**
     * @param  FairTeamDto[]  $teams
     * @param  array<string,int>|null  $deltas
     * @return float
     */
    public function getAvgSkillDelta(array $teams, ?array $deltas = null) : float {
        $deltas ??= $this->calculateDeltas($teams);
        $count = count($deltas);
        if ($count === 0) {
            return 0;
        }
        return array_sum($deltas) / $count;
    }

    /**
     * @param  FairTeamDto[]  $teams
     * @param  array<string,int>|null  $deltas
     * @return int
     */
    public function getTotalSkillDelta(array $teams, ?array $deltas = null) : int {
        $deltas ??= $this->calculateDeltas($teams);
        return array_sum($deltas);
    }

    /**
     * @param  FairTeamDto[]  $teams
     * @param  array<string,int>|null  $deltas
     * @return float
     */
    public function getSkillDeltaStdDev(array $teams, ?array $deltas = null) : float {
        $deltas ??= $this->calculateDeltas($teams);

        $mean = $this->getMeanSkillDelta($teams, $deltas);

        $sum = 0;
        foreach ($deltas as $delta) {
            $meanDiff = $delta - $mean;
            $sum += $meanDiff * $meanDiff;
        }

        return sqrt($sum / count($deltas));
    }

    /**
     * @param  FairTeamDto[]  $teams
     * @param  array<string,int>|null  $deltas
     * @return float
     */
    public function getMeanSkillDelta(array $teams, ?array $deltas = null) : float {
        $deltas ??= $this->calculateDeltas($teams);
        sort($deltas);
        $count = count($deltas);
        $mid = $count / 2;
        if ($count % 2 === 0) {
            return ($deltas[(int) floor($mid)] + $deltas[(int) ceil($mid)]) / 2;
        }
        return $deltas[(int) $mid];
    }
}
