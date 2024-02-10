<?php

namespace App\Tools\GameLoading;

use App\Core\App;
use InvalidArgumentException;
use Throwable;

class GameLoader
{

	/**
	 * @param string $system
	 * @param array  $data
	 *
	 * @return array<string, string|numeric> Metadata
	 */
	public function loadGame(string $system, array $data): array {
		$loader = $this->findGameLoader($system);
		if (!isset($loader)) {
			throw new InvalidArgumentException('Cannot find loader for system - ' . $system);
		}

		return $loader->loadGame($data);
	}

	/**
	 * Find a game loader service based on system
	 *
	 * @param string $system
	 *
	 * @return LoaderInterface|null
	 */
	private function findGameLoader(string $system): ?LoaderInterface {
		try {
			/** @var LoaderInterface $loader */
			$loader = App::getService($system . '.gameLoader');
			return $loader;
		} catch (Throwable) {
			return null;
		}
	}

}