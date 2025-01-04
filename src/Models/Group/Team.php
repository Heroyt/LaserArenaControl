<?php

namespace App\Models\Group;

use JsonSerializable;
use RuntimeException;

class Team implements JsonSerializable
{
    /** @var Player[] */
    private array $players = [];
    /** @var array<int, int> */
    private array $colors = [];
    private int $color;

    public function __construct(
      public readonly string $id,
      public string          $name,
      public string          $system,
    ) {}

    public function addPlayer(Player ...$players) : static {
        foreach ($players as $player) {
            $this->players[$player->asciiName] = $player;
        }
        return $this;
    }

    public function addColor(int $color) : static {
        if (!isset($this->colors[$color])) {
            $this->colors[$color] = 0;
        }
        $this->colors[$color]++;
        arsort($this->colors);
        $this->color = array_key_first($this->colors);
        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize() : array {
        return [
          'id'      => $this->id,
          'name'    => $this->name,
          'system'  => $this->system,
          'color'   => $this->getColor(),
          'players' => $this->players,
        ];
    }

    /**
     * @return int
     */
    public function getColor() : int {
        if (!isset($this->color)) {
            if (empty($this->colors)) {
                throw new RuntimeException('Cannot get team\'s color.');
            }
            arsort($this->colors);
            $this->color = array_key_first($this->colors);
        }
        return $this->color;
    }

    /**
     * @return Player[]
     */
    public function getPlayers() : array {
        return $this->players;
    }
}
