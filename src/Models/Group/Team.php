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
    ) {
    }

    public function addPlayer(Player ...$players): static {
        foreach ($players as $player) {
            $this->players[$player->asciiName] = $player;
        }
        return $this;
    }

    public function addColor(int $color): static {
        if (!isset($this->colors[$color])) {
            $this->colors[$color] = 0;
        }
        $this->colors[$color]++;
        arsort($this->colors);
        $this->color = array_key_first($this->colors);
        return $this;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize(): array {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'system'  => $this->system,
            'color'   => $this->getColor(),
            'players' => $this->getPlayers(),
        ];
    }

    /**
     * @return int
     */
    public function getColor(): int {
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
    public function getPlayers(): array {
        return $this->players;
    }
}
