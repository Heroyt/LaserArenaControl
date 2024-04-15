<?php

namespace App\Gate\Screens;

use App\Gate\Models\MusicGroupDto;
use App\Models\MusicMode;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class MusicModesScreen extends GateScreen implements ReloadTimerInterface
{

    use WithReloadTimer;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Seznam hudebních módů', context: 'gate.screens');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.music';
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $modes = [];
        foreach (MusicMode::getAll() as $music) {
            $group = $music->group ?? $music->name;
            $modes[$group] ??= new MusicGroupDto($group);
            $modes[$group]->music[] = $music;
        }
        return $this->view(
          'gate/screens/musicModes',
          [
            'musicModes' => $modes,
            'addCss'     => ['gate/musicModes.css'],
            'addJs'      => ['gate/musicModes.js'],
          ]
        );
    }
}