<?php

namespace App\Gate\Widgets;

use App\GameModels\Game\Game;
use DateTimeInterface;

interface WidgetInterface
{
    public function refresh(): static;

    /**
     * @param  Game|null  $game
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return array
     */
    public function getData(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []): array;

    /**
     * @param  Game|null  $game
     * @param  DateTimeInterface|null  $date
     * @param  string[]|null  $systems
     * @return string
     */
    public function getHash(?Game $game = null, ?DateTimeInterface $date = null, ?array $systems = []): string;

    public function getTemplate(): string;

    public function getSettingsTemplate(): string;
}
