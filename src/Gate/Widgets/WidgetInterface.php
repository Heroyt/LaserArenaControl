<?php

namespace App\Gate\Widgets;

use App\GameModels\Game\Game;
use DateTimeInterface;

interface WidgetInterface
{
    public function refresh() : static;

    /**
     * @template G of Game
     * @param  G|null  $game
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return array<string,mixed>
     */
    public function getData(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : array;

    /**
     * @template G of Game
     * @param  G|null  $game
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return string
     */
    public function getHash(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []) : string;

    public function getTemplate() : string;

    public function getSettingsTemplate() : string;
}
