<?php

declare(strict_types=1);

namespace App\Gate\Screens\Results;

use Lsr\Core\Requests\Dto\ErrorResponse;
use Psr\Http\Message\ResponseInterface;

class ResultsHiddenScreen extends AbstractResultsScreen
{
    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Skryté výsledky', context: 'screens', domain: 'gate');
    }

    public static function getDescription() : string {
        return lang(
                   'Obrazovka zobrazí jen zprávu, že výsledky jsou skryté.',
          context: 'screens.description',
          domain : 'gate'
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.results.hidden';
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
          'gate/screens/results/hidden',
          [
            'game'   => $game,
            'mode'   => $game->mode,
            'addJs'  => ['gate/resultsHidden.js'],
            'addCss' => ['gate/results.css'],
          ]
        );
    }
}
