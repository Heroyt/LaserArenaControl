<?php

namespace App\Tools\GameLoading;

use App\Core\Info;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Spiral\RoadRunner\Metrics\Metrics;

/**
 *
 */
class Evo5GameLoader extends LasermaxxGameLoader
{
    use MusicLoading;

    public const DI_NAME = 'evo5.gameLoader';
    public const MUSIC_FILE = LMX_DIR.'music/evo5.mp3';

    public function __construct(
      private readonly Latte   $latte,
      private readonly Metrics $metrics,
    ) {}

    /**
     * Prepare a game for loading
     *
     * @param  array{
     *     music?: numeric,
     *     groupSelect?:numeric|'new',
     *     tableSelect?:numeric,
     *     game-mode?:numeric,
     *     variation?:array<numeric,string>,
     *     player?:array{name:string,team?:string,vip?:numeric-string,code:string}[],
     *     team?:array{name:string}[]
     * }  $data
     *
     * @return array<string,string|numeric> Metadata
     * @throws TemplateDoesNotExistException
     */
    public function loadGame(array $data) : array {
        $loadData = $this->loadLasermaxxGame($data);

        // Render the game info into a load file
        $content = $this->latte->viewToString('gameFiles/evo5', $loadData);
        $loadDir = LMX_DIR.Info::get('evo5_load_file', 'games/');
        if (file_exists($loadDir) && is_dir($loadDir)) {
            file_put_contents($loadDir.'0000.game', $content);
        }

        // Set up a correct music file
        if (isset($loadData['meta']['music'])) {
            $start = microtime(true);
            $this->loadOrPlanMusic((int) $loadData['meta']['music']);
            $this->metrics->set('load_music_time', (microtime(true) - $start) * 1000, ['evo5']);
        }

        return $loadData['meta'];
    }
}