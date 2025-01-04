<?php

namespace App\Tools\GameLoading;

use App\Core\App;
use InvalidArgumentException;
use Throwable;

/**
 *
 */
class GameLoader
{
    /** @var array<string, LoaderInterface> */
    private array $loaders = [];

    /**
     * @param  string  $system
     * @param  array<string,mixed>  $data
     *
     * @return array<string, string|numeric> Metadata
     */
    public function loadGame(string $system, array $data) : array {
        $loader = $this->findGameLoader($system);
        if (!isset($loader)) {
            throw new InvalidArgumentException('Cannot find loader for system - '.$system);
        }

        return $loader->loadGame($data);
    }

    /**
     * Find a game loader service based on system
     *
     * @param  string  $system
     *
     * @return LoaderInterface|null
     */
    private function findGameLoader(string $system) : ?LoaderInterface {
        try {
            // @phpstan-ignore-next-line
            $this->loaders[$system] ??= App::getService($system.'.gameLoader');
            // @phpstan-ignore-next-line
            return $this->loaders[$system];
        } catch (Throwable) {
            return null;
        }
    }
}
