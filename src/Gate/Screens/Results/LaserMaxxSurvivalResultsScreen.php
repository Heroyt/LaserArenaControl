<?php

namespace App\Gate\Screens\Results;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Game\Lasermaxx\GameModes\Survival;
use App\Gate\Screens\WithGameQR;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Psr\Http\Message\ResponseInterface;

class LaserMaxxSurvivalResultsScreen extends AbstractResultsScreen
{
    use WithGameQR;

    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('LaserMaxx výsledky z módu Survival', context: 'screens', domain: 'gate');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazující výsledky LaserMaxx z módu Survival.',
          context: 'screens.description',
          domain : 'gate'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.results.lasermaxx.survival';
    }

    public function isActive() : bool {
        try {
            return parent::isActive() && $this->game?->mode instanceof Survival;
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
          'gate/screens/results/lasermaxxSurvival',
          [
            'game'   => $game,
            'qr'     => $this->getQR($game),
            'mode'   => $game->mode,
            'addCss' => ['gate/results.css'],
          ]
        );
    }
}
