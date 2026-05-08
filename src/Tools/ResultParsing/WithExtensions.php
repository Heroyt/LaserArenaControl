<?php

declare(strict_types=1);

namespace App\Tools\ResultParsing;

use App\Core\App;
use App\GameModels\Game\Game;
use Lsr\Lg\Results\Interface\Models\GameInterface;
use Lsr\Lg\Results\Interface\ResultParserExtensionInterface;

trait WithExtensions
{
    /**
     * @template G of Game
     * @param  G  $game
     * @param  array<string, mixed>  $meta
     *
     * @return void
     */
    protected function processExtensions(GameInterface $game, array $meta) : void {
        $extensionNames = App::getContainer()->findByType(ResultParserExtensionInterface::class);
        foreach ($extensionNames as $extensionName) {
            /** @var ResultParserExtensionInterface $extension */
            $extension = App::getService($extensionName);
            $extension->parse($game, $meta, $this);
        }
    }
}
