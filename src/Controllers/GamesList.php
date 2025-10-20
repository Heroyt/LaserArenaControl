<?php

namespace App\Controllers;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use DateTimeImmutable;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Lg\Results\Enums\GameModeType;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

class GamesList extends Controller
{
    protected string $title = 'Seznam her';
    protected string $description = '';

    public function show(Request $request) : ResponseInterface {
        /** @var string $date */
        $date = $request->getGet('date', 'now');
        $this->params['date'] = new DateTimeImmutable($date);
        $this->params['games'] = GameFactory::getByDate($this->params['date'], true);
        $this->params['gameCountsPerDay'] = GameFactory::getGamesCountPerDay('d.m.Y');
        return $this->view('pages/games-list/index');
    }

    public function game() : ResponseInterface {
        return $this->view('pages/dashboard/index');
    }

    /**
     * @template G of Game
     * @param  G  $game
     *
     * @return bool
     * @throws ValidationException
     * @throws DirectoryCreationException
     */
    public function checkGameTeamScores(Game $game) : bool {
        if ($game->gameType !== GameModeType::TEAM) {
            return true;
        }

        foreach ($game->players as $player) {
            if ($player->score > 0) {
                return true;
            }
        }
        return false;
    }
}
