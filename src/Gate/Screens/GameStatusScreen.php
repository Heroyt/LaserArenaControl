<?php

declare(strict_types=1);

namespace App\Gate\Screens;

use Psr\Http\Message\ResponseInterface;

class GameStatusScreen extends GateScreen
{
    /**
     * @inheritDoc
     */
    public static function getName() : string {
        return lang('Stav hry', context: 'screens', domain: 'gate');
    }

    /**
     * @inheritDoc
     */
    public static function getDiKey() : string {
        return 'gate.screens.game_status';
    }

    /**
     * @inheritDoc
     */
    public function run() : ResponseInterface {
        $hash = '';
        $hash .= $this->getGame()?->start?->getTimestamp() ?? '0';
        foreach (($this->getGame()->players ?? []) as $player) {
            $hash .= $player->vest.'-'.$player->name;
        }
        return $this->view(
          'gate/screens/gameStatus',
          [
            'screenHash' => md5($hash),
            'game'       => $this->getGame(),
            'addJs'      => ['gate/gameStatus.js'],
            'addCss'     => ['gate/gameStatus.css'],
          ]
        );
    }
}
