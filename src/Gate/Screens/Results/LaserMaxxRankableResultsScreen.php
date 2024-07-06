<?php

namespace App\Gate\Screens\Results;

use App\Api\Response\ErrorDto;
use App\Gate\Screens\WithGameQR;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class LaserMaxxRankableResultsScreen extends AbstractResultsScreen
{
    use WithGameQR;

    /**
     * @inheritDoc
     */
    public static function getName(): string {
        return lang('LaserMaxx klasické výsledky', domain: 'gate', context: 'screens');
    }

    public static function getDescription(): string {
        return lang(
            'Obrazovka zobrazující výsledky LaserMaxx z klasických her.',
            domain : 'gate',
            context: 'screens.description'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey(): string {
        return 'gate.screens.results.lasermaxx.rankable';
    }

    /**
     * @inheritDoc
     */
    public function run(): ResponseInterface {
        $game = $this->getGame();

        if (!isset($game)) {
            return $this->respond(new ErrorDto('Cannot show screen without game.'), 412);
        }

        if ($this->reloadTime < 0) {
            $this->setReloadTime($this->getReloadTimer());
        }

        return $this->view(
            'gate/screens/results/lasermaxxRankable',
            [
                'game'   => $game,
                'qr'     => $this->getQR($game),
                'addJs'  => ['gate/results.js'],
                'addCss' => ['gate/results.css'],
            ]
        );
    }
}
