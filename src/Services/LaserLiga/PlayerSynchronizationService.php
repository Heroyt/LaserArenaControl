<?php

declare(strict_types=1);

namespace App\Services\LaserLiga;

use App\Models\Auth\Player;

readonly class PlayerSynchronizationService
{
    public function __construct(
      private PlayerProvider $playerProvider,
    ) {}

    public function syncAllArenaPlayers() : void {
        // Should save all found players
        $this->playerProvider->findAllPublicPlayers();
    }

    public function syncAllLocalPlayers() : void {
        // Get all local players
        $playersAll = Player::getAll();
        // Get all player codes
        /** @var array<string, Player> $players */
        $players = [];
        $codes = [];
        foreach ($playersAll as $player) {
            $codes[] = $player->getCode();
            $players[$player->getCode()] = $player;
        }
        // Find public players - should auto-update all local players
        $publicPlayersAll = $this->playerProvider->findAllPublicPlayersByCodes($codes);
        if ($publicPlayersAll === null) {
            return; // Error
        }

        $publicPlayers = [];
        foreach ($publicPlayersAll as $player) {
            $publicPlayers[$player->getCode()] = $player;
        }

        // Find players that have been removed
        foreach ($players as $code => $player) {
            if (isset($publicPlayers[$code])) {
                continue;
            }

            // Try to find
            $playersNew = $this->playerProvider->findAllPublicPlayersByOldCode($code, true);
            if ($playersNew === null) {
                continue; // Error
            }

            $found = false;
            foreach ($playersNew as $playerNew) {
                if ($player->email === $playerNew->email) {
                    $player->code = $playerNew->code;
                    $player->rank = $playerNew->rank;
                    $player->nickname = $playerNew->nickname;
                    $player->save();
                    $found = true;
                    break;
                }
            }

            // Removed player if not found
            if (!$found) {
                $player->delete();
            }
        }
    }
}
