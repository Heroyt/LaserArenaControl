<?php

namespace App\Services\Evo5;

use App\Core\Info;
use App\Exceptions\GameModeNotFoundException;
use App\Exceptions\InsuficientRegressionDataException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Tools\Lasermaxx\RegressionStatCalculator;
use App\Services\RegressionCalculator;
use DateTimeImmutable;
use JsonException;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use RuntimeException;

/**
 *
 */
class GameSimulator
{
    public const int HIT_STD_DEVIATION = 30;
    public const int DEATH_STD_DEVIATION = 30;
    public const int HIT_OWN_STD_DEVIATION = 5;
    public const int DEATH_OWN_STD_DEVIATION = 5;

    public function __construct(
      private readonly Latte                    $latte,
      private readonly RegressionStatCalculator $regressionCalculator,
    ) {}

    /**
     * @throws GameModeNotFoundException
     * @throws InsuficientRegressionDataException
     * @throws TemplateDoesNotExistException
     */
    public function simulate() : void {
        $loadDir = LMX_DIR.Info::get('evo5_load_file', 'games/');
        $loadFile = $loadDir.'0000.game';
        if (!file_exists($loadFile)) {
            throw new RuntimeException('No game file to simulate');
        }

        $meta = [];
        $players = [];
        $teams = [];

        $start = new DateTimeImmutable();
        $end = new DateTimeImmutable('+ 15 minutes');

        $lives = 9999;
        $ammo = 9999;

        $mode = GameModeFactory::getById(1);

        // Parse 0000.game
        $contents = file_get_contents($loadFile);
        if ($contents === false) {
            throw new RuntimeException('Failed to read load file');
        }
        // Parse file into lines and arguments
        preg_match_all('/([A-Z]+){([^{}]*)}#/', $contents, $matches);
        [, $titles, $argsAll] = $matches;

        foreach ($titles as $key => $title) {
            $args = array_map('trim', explode(',', $argsAll[$key]));

            switch ($title) {
                case 'GROUP':
                    $decodedJson = gzinflate(
                      (string) gzinflate(
                        (string) base64_decode($args[1])
                      )
                    );
                    if ($decodedJson !== false) {
                        try {
                            /** @var array<string,string> $meta Meta data from game */
                            $meta = json_decode($decodedJson, true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException) {
                            // Ignore meta
                        }
                    }
                    break;
                // PACK contains information about vest settings
                // - Vest number
                // - Player name
                // - Team number
                // - ???
                // - VIP
                // - 2 unknown arguments
                case 'PACK':
                    $players[] = [
                      'vest'              => $args[0],
                      'name'              => $args[1],
                      'team'              => $args[2],
                      'vip'               => $args[4],
                      'score'             => 0,
                      'shots'             => 0,
                      'hits'              => 0,
                      'deaths'            => 0,
                      'position'          => 0,
                      'scoreForShots'     => 0,
                      'scoreForBonuses'   => 0,
                      'scoreForPowers'    => 0,
                      'scoreForPodDeaths' => 0,
                      'ammoRemaining'     => 0,
                      'accuracy'          => 0,
                      'podHits'           => 0,
                      'agent'             => 0,
                      'invisibility'      => 0,
                      'machineGun'        => 0,
                      'shield'            => 0,
                      'enemyHits'         => 0,
                      'teammateHits'      => 0,
                      'enemyDeaths'       => 0,
                      'teammateDeaths'    => 0,
                      'lives'             => 0,
                      'scoreForHits'      => 0,
                      'vipHits'           => 0,
                      'playerHits'        => [],
                    ];
                    break;
                // TEAM contains team info
                // - Team number
                // - Team name
                // - Player count
                case 'TEAM':
                    $teams[$args[0]] = [
                      'key'         => $args[0],
                      'name'        => $args[1],
                      'playerCount' => $args[2],
                      'score'       => 0,
                      'position'    => 0,
                    ];
                    break;
            }
        }

        // Simulate game
        $hitsModel = $this->regressionCalculator->getHitsModel(GameModeType::TEAM, $mode);
        $hitsOwnModel = $this->regressionCalculator->getHitsOwnModel($mode);
        $deathsModel = $this->regressionCalculator->getDeathsModel(GameModeType::TEAM, $mode);
        $deathsOwnModel = $this->regressionCalculator->getDeathsOwnModel($mode);

        $gameLength = 15;

        $teamsCounts = [];
        $teamMedians = [];
        foreach ($teams as $key => $team) {
            $teamsCounts[$team['key']] = ['team' => (int) $team['playerCount'], 'enemy' => 0];
            foreach ($teams as $key2 => $team2) {
                if ($key === $key2) {
                    continue;
                }
                $teamsCounts[$team['key']]['enemy'] += (int) $team2['playerCount'];
            }
            $teamMedians[$team['key']] = [
              'hits'      => RegressionCalculator::calculateRegressionPrediction(
                [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                $hitsModel
              ),
              'deaths'    => RegressionCalculator::calculateRegressionPrediction(
                [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                $deathsModel
              ),
              'hitsOwn'   => RegressionCalculator::calculateRegressionPrediction(
                [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                $hitsOwnModel
              ),
              'deathsOwn' => RegressionCalculator::calculateRegressionPrediction(
                [$teamsCounts[$team['key']]['team'], $teamsCounts[$team['key']]['enemy'], $gameLength],
                $deathsOwnModel
              ),
            ];
        }


        $playerScores = [];
        foreach ($players as $key => $player) {
            $players[$key]['enemyHits'] = $this->randomValue(
              $teamMedians[$player['team']]['hits'],
              $this::HIT_STD_DEVIATION
            );
            $players[$key]['teammateHits'] = $this->randomValue(
              $teamMedians[$player['team']]['hitsOwn'],
              $this::HIT_OWN_STD_DEVIATION
            );
            $players[$key]['enemyDeaths'] = $this->randomValue(
              $teamMedians[$player['team']]['deaths'],
              $this::DEATH_STD_DEVIATION
            );
            $players[$key]['teammateDeaths'] = $this->randomValue(
              $teamMedians[$player['team']]['deathsOwn'],
              $this::DEATH_OWN_STD_DEVIATION
            );
            $players[$key]['hits'] = $players[$key]['enemyHits'] + $players[$key]['teammateHits'];
            $players[$key]['deaths'] = $players[$key]['enemyDeaths'] + $players[$key]['teammateDeaths'];
            $players[$key]['accuracy'] = rand(10, 80);
            $players[$key]['shots'] = round($players[$key]['hits'] * (1 + (100 / $players[$key]['accuracy'])));
            $players[$key]['lives'] = $lives - $players[$key]['deaths'];
            $players[$key]['ammoRemaining'] = $ammo - $players[$key]['shots'];
            $players[$key]['score'] =
              (100 * $players[$key]['enemyHits'])
              - (50 * $players[$key]['deaths'])
              - (25 * $players[$key]['teammateHits']);
            $playerScores[$key] = $players[$key]['score'];

            $teams[$player['team']]['score'] += $players[$key]['score'];

            $hitsOwn = $this->randomSumDistribution(
              $players[$key]['teammateHits'],
              $teamsCounts[$player['team']]['team'] - 1
            );
            $hitsEnemy = $this->randomSumDistribution(
              $players[$key]['enemyHits'],
              $teamsCounts[$player['team']]['enemy']
            );

            foreach ($players as $key2 => $player2) {
                if ($key === $key2) {
                    $players[$key]['playerHits'][] = 0;
                    continue;
                }

                if ($player['team'] === $player2['team']) {
                    $players[$key]['playerHits'][] = array_shift($hitsOwn);
                    continue;
                }

                $players[$key]['playerHits'][] = array_shift($hitsEnemy);
            }
        }

        // Positions
        $teamScores = [];
        foreach ($teams as $key => $team) {
            $teamScores[$key] = $team['score'];
        }
        arsort($playerScores);
        arsort($teamScores);
        $i = 1;
        foreach ($teamScores as $key => $score) {
            $teams[$key]['position'] = $i;
            $i++;
        }
        $i = 1;
        foreach ($playerScores as $key => $score) {
            $players[$key]['position'] = $i;
            $i++;
        }


        $content = $this->latte->viewToString(
          'gameFiles/evo5Results',
          [
            'players' => $players,
            'teams' => $teams,
            'meta'  => $meta,
            'start' => $start,
            'end'   => $end,
          ]
        );
        file_put_contents(LMX_DIR.'results/simulated.game', $content);
    }

    private function randomValue(float $median, float $stdDeviation) : int {
        // Generate two random numbers between 0 and 1
        $num1 = rand() / getrandmax();
        $num2 = rand() / getrandmax();

        // Calculate the standard normal deviation
        $z = sqrt(-2 * log($num1)) * cos(2 * M_PI * $num2);

        // Scale and shift the standard normal deviation to get the desired median and standard deviation
        $value = (int) round($median + ($stdDeviation * $z));

        // Make sure that the value is not negative
        if ($value < 0) {
            return 0;
        }
        return $value;
    }

    /**
     * @param  int  $sum
     * @param  int  $count
     * @return int[]
     */
    private function randomSumDistribution(int $sum, int $count) : array {
        // Generate N-1 random partition points
        $partition_points = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $partition_points[] = rand(0, $sum);
        }
        sort($partition_points);

        // Compute the N parts
        $parts = [];
        $parts[] = $partition_points[0];
        for ($i = 1; $i < $count - 1; $i++) {
            $parts[] = $partition_points[$i] - $partition_points[$i - 1];
        }
        $parts[] = $sum - $partition_points[$count - 2];

        return $parts;
    }
}
