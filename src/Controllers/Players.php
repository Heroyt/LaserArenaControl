<?php

namespace App\Controllers;

use App\Models\Auth\Player;
use App\Services\PlayerProvider;
use InvalidArgumentException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class Players extends Controller
{
    public function __construct(
        private readonly PlayerProvider $playerProvider
    ) {
        parent::__construct();
    }

    public function getPlayer(string $code): ResponseInterface {
        try {
            $player = Player::getByCode($code);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['error' => $e->getMessage(), 'code' => $code], 400);
        }
        if (!isset($player)) {
            $player = $this->playerProvider->findPublicPlayerByCode($code);
        }
        if (!isset($player)) {
            return $this->respond(['error' => 'Player not found'], 404);
        }
        return $this->respond($player);
    }

    public function syncPlayer(string $code): ResponseInterface {
        $player = $this->playerProvider->findPublicPlayerByCode($code);
        if (!isset($player)) {
            return $this->respond(['error' => 'Player not found'], 404);
        }
        if (!$player->save()) {
            return $this->respond(['error' => 'Save failed'], 500);
        }
        return $this->respond($player);
    }

    public function find(Request $request): ResponseInterface {
        return $this->respond(
            array_values(
                $this->playerProvider->findPlayersLocal(
                    (string) $request->getGet('search', ''),
                    empty($request->getGet('nomail', ''))
                )
            )
        );
    }

    public function findPublic(Request $request): ResponseInterface {
        return $this->respond(
            $this->playerProvider->findPlayersPublic(
                (string) $request->getGet('search', '')
            )
        );
    }
}
