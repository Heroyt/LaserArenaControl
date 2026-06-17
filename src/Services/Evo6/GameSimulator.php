<?php

declare(strict_types=1);

namespace App\Services\Evo6;

use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\InsufficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Helpers\Math\Random;
use App\Services\GameSimulationState;
use App\Services\RegressionCalculator;
use DateTimeImmutable;
use JsonException;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Lg\Results\Enums\GameModeType;
use RuntimeException;

class GameSimulator
{
    public const int HIT_STD_DEVIATION = 30;
    public const int DEATH_STD_DEVIATION = 30;
    public const int HIT_OWN_STD_DEVIATION = 5;
    public const int DEATH_OWN_STD_DEVIATION = 5;

    public function __construct(
        private readonly Latte                    $latte,
        private readonly RegressionStatCalculator $regressionCalculator,
    ) {
    }

    /**
     * @throws GameModeNotFoundException
     * @throws InsufficientRegressionDataException
     * @throws TemplateDoesNotExistException
     */
    public function simulate(GameSimulationState $state = GameSimulationState::FINISHED): void {
        $loadDir = LMX_DIR . Info::get('evo6_load_file', 'games/');
        $loadFile = $loadDir . '0000.game';
        if ( ! file_exists($loadFile)) {
            throw new RuntimeException('No Evo6 game file to simulate');
        }

        $meta = [];
        /** @var list<array{vest:string,name:string,team:string,vip:bool,birthday:bool,score:int|float,shots:int|float,hits:int,deaths:int,position:int,myLasermaxx:string,activity:int,calories:int,scoreForShots:int,scoreForBonuses:int,scoreForPowers:int,scoreForPodDeaths:int,ammoRemaining:int|float,accuracy:int,podHits:int,enemyHits:int,teammateHits:int,enemyDeaths:int,teammateDeaths:int,lives:int|float,scoreForHits:int|float,vipHits:int,scoreForActivity:int,scoreEncouragement:int,scoreKnockout:int,scoreReality:int,bonusCount:int,penaltyCount:int,scorePenalty:int,playerHits:list<int>}> $players */
        $players = [];
        /** @var array<string,array{key:string,name:string,playerCount:string,score:int|float,position:int}> $teams */
        $teams = [];
        $soloTeam = 2;

        [$start, $end] = $this->getGameTimes($state);

        $lives = 9999;
        $ammo = 9999;

        $mode = GameModeFactory::getById(1);

        $contents = file_get_contents($loadFile);
        if ($contents === false) {
            throw new RuntimeException('Failed to read Evo6 load file');
        }
        preg_match_all('/([A-Z]+){([^{}]*)}#/', $contents, $matches);
        [, $titles, $argsAll] = $matches;

        foreach ($titles as $key => $title) {
            $args = array_map('trim', explode(',', $argsAll[$key]));

            switch ($title) {
                case 'GROUP':
                    $decodedJson = gzinflate(
                        (string)gzinflate(
                            (string)base64_decode($args[1]),
                        ),
                    );
                    if ($decodedJson !== false) {
                        try {
                            /** @var array<string,string> $meta */
                            $meta = json_decode($decodedJson, true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException) {
                            // Ignore meta
                        }
                    }
                    $soloTeam = (int)($args[2] ?? 2);
                    break;
                case 'PACK':
                    $players[] = [
                        'vest' => (string)$args[0],
                        'name' => (string)($args[1] ?? ''),
                        'team' => (string)($args[2] ?? ''),
                        'vip' => (bool)($args[4] ?? false),
                        'birthday' => (bool)($args[7] ?? false),
                        'score' => 0,
                        'shots' => 0,
                        'hits' => 0,
                        'deaths' => 0,
                        'position' => 0,
                        'myLasermaxx' => '',
                        'activity' => 0,
                        'calories' => 0,
                        'scoreForShots' => 0,
                        'scoreForBonuses' => 0,
                        'scoreForPowers' => 0,
                        'scoreForPodDeaths' => 0,
                        'ammoRemaining' => 0,
                        'accuracy' => 0,
                        'podHits' => 0,
                        'enemyHits' => 0,
                        'teammateHits' => 0,
                        'enemyDeaths' => 0,
                        'teammateDeaths' => 0,
                        'lives' => 0,
                        'scoreForHits' => 0,
                        'vipHits' => 0,
                        'scoreForActivity' => 0,
                        'scoreEncouragement' => 0,
                        'scoreKnockout' => 0,
                        'scoreReality' => 0,
                        'bonusCount' => 0,
                        'penaltyCount' => 0,
                        'scorePenalty' => 0,
                        'playerHits' => [],
                    ];
                    break;
                case 'TEAM':
                    $teams[$args[0]] = [
                        'key' => (string)$args[0],
                        'name' => (string)($args[1] ?? ''),
                        'playerCount' => (string)($args[2] ?? '0'),
                        'score' => 0,
                        'position' => 0,
                    ];
                    break;
            }
        }

        // Keep parser metadata validation consistent with the simulated game state.
        $meta['loadTime'] = match ($state) {
            GameSimulationState::LOADED => time(),
            default => $start->getTimestamp() - 60,
        };

        $hitsModel = $this->regressionCalculator->getHitsModel(GameModeType::TEAM, $mode);
        $hitsOwnModel = $this->regressionCalculator->getHitsOwnModel($mode);
        $deathsModel = $this->regressionCalculator->getDeathsModel(GameModeType::TEAM, $mode);
        $deathsOwnModel = $this->regressionCalculator->getDeathsOwnModel($mode);

        $gameLength = 15;

        /** @var array<string,array{team:int,enemy:int}> $teamsCounts */
        $teamsCounts = [];
        /** @var array<string,array{hits:float,deaths:float,hitsOwn:float,deathsOwn:float}> $teamMedians */
        $teamMedians = [];
        foreach ($teams as $key => $team) {
            $teamsCounts[$team['key']] = ['team' => (int)$team['playerCount'], 'enemy' => 0];
            foreach ($teams as $key2 => $team2) {
                if ($key === $key2) {
                    continue;
                }
                $teamsCounts[$team['key']]['enemy'] += (int)$team2['playerCount'];
            }
            $teamMedians[$team['key']] = [
                'hits' => RegressionCalculator::calculateRegressionPrediction(
                    [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                    $hitsModel,
                ),
                'deaths' => RegressionCalculator::calculateRegressionPrediction(
                    [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                    $deathsModel,
                ),
                'hitsOwn' => RegressionCalculator::calculateRegressionPrediction(
                    [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                    $hitsOwnModel,
                ),
                'deathsOwn' => RegressionCalculator::calculateRegressionPrediction(
                    [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                    $deathsOwnModel,
                ),
            ];
        }

        $playerScores = [];
        foreach ($players as $key => $player) {
            $players[$key]['enemyHits'] = Random::randomNormal(
                $teamMedians[$player['team']]['hits'],
                self::HIT_STD_DEVIATION,
            );
            $players[$key]['teammateHits'] = Random::randomNormal(
                $teamMedians[$player['team']]['hitsOwn'],
                self::HIT_OWN_STD_DEVIATION,
            );
            $players[$key]['enemyDeaths'] = Random::randomNormal(
                $teamMedians[$player['team']]['deaths'],
                self::DEATH_STD_DEVIATION,
            );
            $players[$key]['teammateDeaths'] = Random::randomNormal(
                $teamMedians[$player['team']]['deathsOwn'],
                self::DEATH_OWN_STD_DEVIATION,
            );
            $players[$key]['hits'] = $players[$key]['enemyHits'] + $players[$key]['teammateHits'];
            $players[$key]['deaths'] = $players[$key]['enemyDeaths'] + $players[$key]['teammateDeaths'];
            $players[$key]['accuracy'] = rand(10, 80);
            $players[$key]['shots'] = round($players[$key]['hits'] * (1 + (100 / $players[$key]['accuracy'])));
            $players[$key]['lives'] = max(0, $lives - $players[$key]['deaths']);
            $players[$key]['ammoRemaining'] = max(0, $ammo - $players[$key]['shots']);
            $players[$key]['scoreForHits'] = (100 * $players[$key]['enemyHits'])
                - (50 * $players[$key]['enemyDeaths']);
            $players[$key]['score'] = $players[$key]['scoreForHits']
                - (25 * $players[$key]['teammateHits'])
                - (50 * $players[$key]['teammateDeaths']);
            $playerScores[$key] = $players[$key]['score'];

            $teams[$player['team']]['score'] += $players[$key]['score'];

            $hitsOwn = Random::randomSumDistribution(
                $players[$key]['teammateHits'],
                max(0, $teamsCounts[$player['team']]['team'] - 1),
            );
            $hitsEnemy = Random::randomSumDistribution(
                $players[$key]['enemyHits'],
                $teamsCounts[$player['team']]['enemy'],
            );

            foreach ($players as $key2 => $player2) {
                if ($key === $key2) {
                    $players[$key]['playerHits'][] = 0;
                    continue;
                }

                if ($player['team'] === $player2['team']) {
                    $players[$key]['playerHits'][] = array_shift($hitsOwn) ?? 0;
                    continue;
                }

                $players[$key]['playerHits'][] = array_shift($hitsEnemy) ?? 0;
            }
        }

        $teamScores = [];
        foreach ($teams as $key => $team) {
            $teamScores[$key] = $team['score'];
        }
        arsort($playerScores);
        arsort($teamScores);
        $i = 1;
        foreach ($teamScores as $key => $score) {
            $teams[$key]['position'] = $i++;
        }
        $i = 1;
        foreach ($playerScores as $key => $score) {
            $players[$key]['position'] = $i++;
        }

        $content = $this->latte->viewToString(
            'gameFiles/evo6Results',
            [
                'players' => $players,
                'teams' => $teams,
                'meta' => $meta,
                'start' => $start,
                'end' => $end,
                'soloTeam' => $soloTeam,
            ],
        );
        file_put_contents(LMX_DIR . 'results/simulated.game', $content);
    }

    /**
     * @return array{DateTimeImmutable,DateTimeImmutable}
     */
    private function getGameTimes(GameSimulationState $state): array {
        return match ($state) {
            GameSimulationState::FINISHED => [
                new DateTimeImmutable('- 16 minutes'),
                new DateTimeImmutable('- 30 seconds'),
            ],
            GameSimulationState::LOADED => [
                new DateTimeImmutable('+ 20 minutes'),
                new DateTimeImmutable('+ 35 minutes'),
            ],
            GameSimulationState::STARTED => [
                new DateTimeImmutable('- 1 minute'),
                new DateTimeImmutable('+ 14 minutes'),
            ],
        };
    }
}
