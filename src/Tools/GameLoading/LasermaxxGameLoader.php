<?php

namespace App\Tools\GameLoading;

use App\Core\App;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\GameModes\CustomLoadMode;
use App\Models\MusicMode;
use App\Models\Playlist;
use App\Models\System;
use Lsr\Core\Templating\Latte;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 * @phpstan-type GameData array{
 *      playlist?:numeric,
 *      use-playlist?:numeric,
 *      music?: numeric,
 *      groupSelect?:numeric|'new'|'new-custom',
 *      groupName?:string,
 *      tableSelect?:numeric,
 *      game-mode?:numeric,
 *      variation?:array<numeric,string>,
 *      player?:array{name:string,team?:string,vip?:numeric-string,birthday?:numeric-string,code:string}[],
 *      team?:array{name:string}[],
 *      mode?:string,
 *      meta?:array<string,mixed>
 *   }
 */
abstract class LasermaxxGameLoader implements LoaderInterface
{
    use GroupLoading;

    public System $system;

    public function __construct(
      protected readonly Latte   $latte,
      protected readonly Metrics $metrics,
    ) {}

    /**
     * @param  non-empty-string  $system
     */
    public function loadMusic(
      int    $musicId,
      string $musicFile,
      string $system = 'evo5',
      ?float $timeSinceStart = null
    ) : void {
        $startPlay = microtime(true);
        $endPlay = null;
        try {
            $music = MusicMode::get($musicId);
            if (!file_exists($music->fileName)) {
                App::getInstance()->getLogger()->warning('Music file does not exist - '.$music->fileName);
            }
            else if (!copy($music->fileName, $musicFile)) {
                App::getInstance()->getLogger()->warning('Music copy failed - '.$music->fileName);
            }
            $endPlay = microtime(true);

            if ($music->introFile !== null) {
                $startIntro = microtime(true);
                $introFile = str_replace('.mp3', '.intro.mp3', $musicFile);
                if (!file_exists($music->introFile)) {
                    App::getInstance()->getLogger()->warning('Music file does not exist - '.$music->introFile);
                }
                else if (!copy($music->introFile, $introFile)) {
                    App::getInstance()->getLogger()->warning('Music copy failed - '.$music->introFile);
                }
                $endIntro = microtime(true);
            }

            if ($music->endingFile !== null) {
                $startEnding = microtime(true);
                $endingFile = str_replace('.mp3', '.gameover.mp3', $musicFile);
                if (!file_exists($music->endingFile)) {
                    App::getInstance()->getLogger()->warning('Music file does not exist - '.$music->endingFile);
                }
                else if (!copy($music->endingFile, $endingFile)) {
                    App::getInstance()->getLogger()->warning('Music copy failed - '.$music->endingFile);
                }
                $endEnding = microtime(true);
            }
        } catch (ModelNotFoundException | ValidationException | DirectoryCreationException) {
            // Not critical, doesn't need to do anything
        }
        $end = microtime(true);
        $endPlay ??= $end;
        $this->metrics->set('load_music_time', ($endPlay - $startPlay) * 1000, [$system, 'play']);
        if (isset($startIntro, $endIntro)) {
            $this->metrics->set('load_music_time', ($endIntro - $startIntro) * 1000, [$system, 'intro']);
        }
        if (isset($startEnding, $endEnding)) {
            $this->metrics->set('load_music_time', ($endEnding - $startEnding) * 1000, [$system, 'intro']);
        }
        if (isset($timeSinceStart)) {
            $this->metrics->set('music_time_since_load', ($end - $timeSinceStart) * 1000, [$system]);
        }
    }

    /**
     * @param  int  $musicId
     * @param  string  $musicFile
     * @param  non-empty-string  $system
     * @return void
     */
    public function loadArmedMusic(
      int    $musicId,
      string $musicFile,
      string $system = 'evo5',
    ) : void {
        $start = microtime(true);
        try {
            $music = MusicMode::get($musicId);
            if ($music->armedFile === null) {
                return;
            }
            $armedFile = str_replace('.mp3', '.armed.mp3', $musicFile);
            if (!file_exists($music->armedFile)) {
                App::getInstance()->getLogger()->warning('Music file does not exist - '.$music->armedFile);
            }
            else if (!copy($music->armedFile, $armedFile)) {
                App::getInstance()->getLogger()->warning('Music copy failed - '.$music->armedFile);
            }
        } catch (ModelNotFoundException | ValidationException | DirectoryCreationException) {
            // Not critical, doesn't need to do anything
        }
        $end = microtime(true);
        $this->metrics->set('load_music_time', ($end - $start) * 1000, [$system, 'armed']);
    }

    /**
     * @param  GameData  $data
     *
     * @return LasermaxxLoadData
     */
    protected function loadLasermaxxGame(array $data) : LasermaxxLoadData {
        $loadData = new LasermaxxLoadData(
          meta: [
                  'music'    => empty($data['music']) ? null : $data['music'],
                  'mode'     => $data['mode'] ?? '',
                  'loadTime' => time(),
                  ...($data['meta'] ?? []),
                ],
        );

        /** @var array<int,string> $hashData */
        $hashData = [];

        $this->prepareGroup($loadData, $data);

        if (!empty($data['tableSelect'])) {
            $loadData->meta['table'] = $data['tableSelect'];
        }

        try {
            $mode = GameModeFactory::getById((int) ($data['game-mode'] ?? 0));
        } catch (GameModeNotFoundException) {
        }
        if (empty($loadData->meta['mode']) && isset($mode)) {
            $loadData->meta['mode'] = $mode->loadName;
            if (!empty($data['variation'])) {
                uksort($data['variation'], static fn($a, $b) => ((int) $a) - ((int) $b));
                $loadData->meta['variations'] = [];
                foreach ($data['variation'] as $id => $suffix) {
                    $loadData->meta['variations'][$id] = $suffix;
                    $loadData->meta['mode'] .= $suffix;
                }
            }
        }

        /** @var array<numeric-string, int> $teams */
        $teams = [];

        // Validate and parse players
        foreach ($data['player'] ?? [] as $vest => $player) {
            if (empty(trim($player['name']))) {
                continue;
            }
            if (!isset($player['team']) || $player['team'] === '') {
                if (!isset($mode) || $mode->isTeam()) {
                    continue;
                }
                // Default team for solo game
                $player['team'] = '2';
            }

            $asciiName = substr($this->escapeName($player['name']), 0, 12);
            if ($player['name'] !== $asciiName) {
                $loadData->meta['p'.$vest.'n'] = $player['name'];
            }
            if (!empty($player['code'])) {
                $loadData->meta['p'.$vest.'u'] = $player['code'];
            }
            $hashData[(int) $vest] = $vest.'-'.$asciiName;
            $loadData->players[(int) $vest] = new LasermaxxLoadPlayerData(
              (string) $vest,
              $asciiName,
              (string) $player['team'],
              ((int) ($player['vip'] ?? 0)) === 1,
              birthday: ((int) ($player['birthday'] ?? 0)) === 1,
            );
            if (!isset($teams[(string) $player['team']])) {
                $teams[(string) $player['team']] = 0;
            }
            $teams[(string) $player['team']]++;
        }

        foreach ($data['team'] ?? [] as $key => $team) {
            $asciiName = $this->escapeName($team['name']);
            if ($team['name'] !== $asciiName) {
                $loadData->meta['t'.$key.'n'] = $team['name'];
            }
            $loadData->teams[] = new LasermaxxLoadTeamData(
              $key,
              $asciiName,
              (int) ($teams[(string) $key] ?? 0),
            );
        }

        if (isset($mode) && $mode instanceof CustomLoadMode) {
            $loadData = $mode->modifyGameDataBeforeLoad($loadData, $data);
        }

        $loadData->filterTeams();
        ksort($hashData);
        $loadData->sortPlayers();
        $loadData->players = array_values($loadData->players);
        assert(is_string($loadData->meta['mode']), 'Mode name must be set and be a string');
        $loadData->meta['hash'] = md5($loadData->meta['mode'].';'.implode(';', $hashData));


        // Choose random music ID if a group is selected
        if (!empty($data['use-playlist']) && !empty($data['playlist'])) {
            try {
                $playlist = Playlist::get((int) $data['playlist']);
                $musicIds = $playlist->getMusicIds();
                if (!empty($musicIds)) {
                    $loadData->meta['music'] = (int) $musicIds[array_rand($musicIds)];
                }
            } catch (ModelNotFoundException | ValidationException) {
            }
        }
        if (
          isset($loadData->meta['music']) &&
          is_string($loadData->meta['music']) &&
          str_starts_with($loadData->meta['music'], 'g-')
        ) {
            $musicIds = array_slice(explode('-', $loadData->meta['music']), 1);
            $loadData->meta['music'] = (int) $musicIds[array_rand($musicIds)];
        }
        return $loadData;
    }

    /**
     * Replaces all unwanted characters in the player/team name
     *
     * @param  string  $name
     * @return string
     */
    public function escapeName(string $name) : string {
        // Remove UTF-8 characters
        $name = Strings::toAscii($name);
        // Remove key characters
        return str_replace(
          [
            '#',
            ',',
            '}',
            '{',
          ],
          [
            '+',
            '.',
            ']',
            '[',
          ],
          $name
        );
    }
}
