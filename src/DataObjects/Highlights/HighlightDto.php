<?php

declare(strict_types=1);

namespace App\DataObjects\Highlights;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\Services\GameHighlight\GameHighlightService;
use DateTimeInterface;
use Throwable;

/**
 * @template G of Game
 */
class HighlightDto
{
    /**
     * @var G|null
     */
    private ?Game $game;

    /**
     * @param  string  $code
     * @param  DateTimeInterface  $datetime
     * @param  int  $rarity
     * @param  GameHighlightType  $type
     * @param  string  $description
     * @param  array{name:string,label:string,user:string|null}[]|null  $players
     * @param  GameHighlight|null  $object
     */
    public function __construct(
        public readonly string            $code,
        public readonly DateTimeInterface $datetime,
        public readonly int               $rarity,
        public readonly GameHighlightType $type,
        public readonly string            $description,
        public readonly ?array            $players = null,
        public readonly ?GameHighlight    $object = null,
    ) {
    }

    /**
     * @return G|null
     * @throws Throwable
     */
    public function getGame(): ?Game {
        /** @phpstan-ignore assign.propertyType */
        $this->game ??= GameFactory::getByCode($this->code);
        /** @phpstan-ignore return.type */
        return $this->game;
    }

    public function getFormattedDescription(): string {
        return preg_replace_callback(
            GameHighlightService::PLAYER_REGEXP,
            static function (array $matches) {
                $playerName = $matches[1];
                $label = $matches[2] ?? $playerName;

                return '<strong class="player-name" data-player="' . $playerName . '">' . $label . '</strong>';
            },
            $this->description,
        ) ?? $this->description;
    }

    public function getIcon(): string {
        if (isset($this->object) && $this->object instanceof TrophyHighlight) {
            return $this->object->player->trophy::getFields()[$this->object->value]['icon'];
        }
        return $this->type->getIcon();
    }
}
