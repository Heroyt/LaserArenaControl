<?php

namespace App\Api\DataObjects\LigaPlayer;

/**
 *
 */
class LigaPlayerData
{
    public int $id;
    public string $nickname;
    public string $code;
    public ?int $arena;
    public string $email;
    public LigaPlayerStats $stats;
    /** @var LigaPlayerConnection[] */
    public array $connections = [];
    /**
     * @var string[]|null
     */
    public ?array $codeHistory = null;

    public function addConnection(LigaPlayerConnection $connection): void {
        $this->connections[] = $connection;
    }

    public function removeConnection(LigaPlayerConnection $connection): void {
        foreach ($this->connections as $key => $test) {
            if ($test === $connection) {
                unset($this->connections[$key]);
                return;
            }
        }
    }
}
