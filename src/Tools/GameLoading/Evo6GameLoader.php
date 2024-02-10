<?php

namespace App\Tools\GameLoading;

use App\Core\Info;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;

class Evo6GameLoader extends LasermaxxGameLoader
{

	public function __construct(private readonly Latte $latte) {
	}

	/**
	 * Prepare a game for loading
	 *
	 * @param array{
	 *     music?: numeric,
	 *     groupSelect?:numeric|'new',
	 *     tableSelect?:numeric,
	 *     game-mode?:numeric,
	 *     variation?:array<numeric,string>,
	 *     player?:array{name:string,team?:string,vip?:numeric-string,code:string}[],
	 *     team?:array{name:string}[]
	 * } $data
	 *
	 * @return array<string,string|numeric> Metadata
	 * @throws TemplateDoesNotExistException
	 */
	public function loadGame(array $data): array {
		$loadData = $this->loadLasermaxxGame($data);

		// Render the game info into a load file
		$content = $this->latte->viewToString('gameFiles/evo6', $loadData);
		$loadDir = LMX_DIR . Info::get('evo6_load_file', 'games/');
		if (file_exists($loadDir) && is_dir($loadDir)) {
			file_put_contents($loadDir . '0000.game', $content);
		}

		// Set up a correct music file
		if (isset($loadData['meta']['music'])) {
			$this->loadMusic((int)$loadData['meta']['music'], LMX_DIR . 'music/evo6.mp3');
		}

		return $loadData['meta'];
	}
}