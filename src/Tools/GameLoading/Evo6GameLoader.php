<?php

namespace App\Tools\GameLoading;

use App\Core\Info;
use Lsr\Exceptions\TemplateDoesNotExistException;

/**
 * @phpstan-import-type GameData from LasermaxxGameLoader
 * @phpstan-import-type MetaLoadData from LasermaxxLoadData
 */
class Evo6GameLoader extends LasermaxxGameLoader
{
    use MusicLoading;

    public const string DI_NAME = 'evo6.gameLoader';
    public const string MUSIC_FILE = LMX_DIR.'music/evo6.mp3';

    /**
     * Prepare a game for loading
     *
     * @param  GameData  $data
     *
     * @return MetaLoadData Metadata
     * @throws TemplateDoesNotExistException
     */
    public function loadGame(array $data) : array {
        $loadData = $this->loadLasermaxxGame($data);

        // Render the game info into a load file
        $content = $this->latte->viewToString('gameFiles/evo6', $loadData->getParams());
        $loadDir = LMX_DIR.Info::get('evo6_load_file', 'games/');
        if (file_exists($loadDir) && is_dir($loadDir)) {
            file_put_contents(trailingSlashIt($loadDir).'0000.game', $content);
        }

        // Set up a correct music file
        if (isset($loadData->meta['music'])) {
            $this->loadOrPlanMusic((int) $loadData->meta['music'], 'evo6');
        }

        return $loadData->meta;
    }
}
