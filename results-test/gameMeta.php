<?php

use App\Core\App;
use App\GameModels\Game\Lasermaxx\Evo6\Player;
use Lsr\Lg\Results\ResultsParser;
use Symfony\Component\Serializer\Serializer;

const ROOT = __DIR__ . '/../';
require ROOT . 'include/load.php';

$files = glob(ROOT . '/results-test/*_archive.game');

$resultParser = App::getServiceByType(ResultsParser::class);
$serializer = App::getServiceByType(Serializer::class);

foreach ($files as $file) {
    $resultParser->setFile($file);
    $game = $resultParser->parse();
    $game->code = 'test' . $game->resultsFile;

    $metaFile = str_replace('.game', '.meta', $file);
    $jsonFile = str_replace('.game', '.out.json', $file);
    $json = $game->jsonSerialize();
    file_put_contents($jsonFile, $serializer->serialize($game, 'json'));

    $meta = [
      'system' => $game::SYSTEM,
      'playerCount' => count($json['players']),
      'teamCount' => count($json['teams']),
      'mode' => $json['mode']['name'],
      'modeType' => $json['mode']['type'],
      'timing' => $json['timing'],
      'scoring' => $json['scoring'],
      'start' => $json['start'],
      'end' => $json['end'],
      'players' => [],
      'teams' => [],
    ];

    foreach ($game->players as $player) {
        $playerData = [
          'name' => $player->name,
          'user' => $player->user?->id,
          'code' => $player->user?->code,
          'score' => $player->score,
          'shots' => $player->shots,
          'hits' => $player->hits,
          'accuracy' => $player->accuracy,
          'deaths' => $player->deaths,
          'position' => $player->position,
          'team' => $player->teamNum,
          'hitsPlayer' => [],
        ];
        if ($player instanceof \App\GameModels\Game\Lasermaxx\Evo5\Player) {
            $playerData['shotPoints'] = $player->shotPoints;
            $playerData['scoreBonus'] = $player->scoreBonus;
            $playerData['scorePowers'] = $player->scorePowers;
            $playerData['scoreMines'] = $player->scoreMines;
            $playerData['minesHits'] = $player->minesHits;
            $playerData['hitsOther'] = $player->hitsOther;
            $playerData['hitsOwn'] = $player->hitsOwn;
            $playerData['deathsOwn'] = $player->deathsOwn;
            $playerData['deathsOther'] = $player->deathsOther;
            $playerData['bonus'] = (array) $player->bonus;
        }
        if ($player instanceof Player) {
            $playerData['shotPoints'] = $player->shotPoints;
            $playerData['scoreBonus'] = $player->scoreBonus;
            $playerData['scorePowers'] = $player->scorePowers;
            $playerData['scoreMines'] = $player->scoreMines;
            $playerData['minesHits'] = $player->minesHits;
            $playerData['hitsOther'] = $player->hitsOther;
            $playerData['hitsOwn'] = $player->hitsOwn;
            $playerData['deathsOwn'] = $player->deathsOwn;
            $playerData['deathsOther'] = $player->deathsOther;
            $playerData['bonusCount'] = $player->getBonusCount();
        }
        foreach ($player->getHitsPlayers() as $target => $hit) {
            $playerData['hitsPlayer'][$target] = $hit->count;
        }
        $meta['players'][$player->vest] = $playerData;
    }
    foreach ($json['teams'] as $team) {
        $teamData = [
          'name' => $team['name'],
          'color' => $team['color'],
          'score' => $team['score'],
          'position' => $team['position'],
        ];

        $meta['teams'][$team['color']] = $teamData;
    }

    file_put_contents($metaFile, serialize($meta));
}
