<?php

namespace App\Tools\GameLoading;

use App\Core\App;
use App\Models\System;
use App\Models\SystemType;
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
     * @param  array<string,mixed>  $data
     *
     * @return array<string, string|numeric> Metadata
     */
    public function loadGame(string | int | System $system, array $data) : array {
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
    private function findGameLoader(string | int | System $system) : ?LoaderInterface {
        if (is_numeric($system)) {
            $system = System::get((int) $system);
        }
        elseif (is_string($system)) {
            $type = SystemType::tryFrom($system);
            if ($type === null) {
                throw new InvalidArgumentException('Invalid system type');
            }
            $systems = System::getForType($type);
            if (empty($systems)) {
                throw new InvalidArgumentException('Invalid system type');
            }
            /** @var System $system */
            $system = first($systems);
        }

        $systemStr = $system->type->value;

        try {
            $loader = App::getService($systemStr.'.gameLoader');
            assert($loader instanceof LoaderInterface);
            $loader->system = $system;
            $this->loaders[$systemStr] ??= $loader;
            return $this->loaders[$systemStr];
        } catch (Throwable) {
            return null;
        }
    }
}
