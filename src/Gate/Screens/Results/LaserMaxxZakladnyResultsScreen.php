<?php

namespace App\Gate\Screens\Results;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Lasermaxx\Evo5\GameModes\Zakladny;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class LaserMaxxZakladnyResultsScreen extends AbstractResultsScreen
{
    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('LaserMaxx výsledky z módu Základny', context: 'screens', domain: 'gate');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující výsledky LaserMaxx z módu Základny.',
          context: 'screens.description',
          domain : 'gate'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.results.lasermaxx.zakladny';
    }

    public function isActive() : bool {
        try {
            return parent::isActive() && $this->game?->mode instanceof Zakladny;
        } catch (GameModeNotFoundException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $game = $this->game;

        if (!isset($game)) {
            return $this->respond(new ErrorResponse('Cannot show screen without game.'), 412);
        }

        if ($this->reloadTime < 0) {
            $this->setReloadTime($this->getReloadTimer());
        }

        return $this->view(
          'gate/screens/results/lasermaxxZakladny',
          [
            'game'   => $game,
            'mode'   => $game->mode,
            'addCss' => ['gate/resultsZakladny.css'],
          ]
        );
    }
}
