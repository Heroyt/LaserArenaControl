<?php

namespace App\Models;

use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use Lsr\Core\App;
use Lsr\Core\Caching\Cache;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;
use Lsr\Helpers\Tools\Strings;
use Nette\Caching\Cache as CacheParent;
use Throwable;

/**
 *
 */
#[PrimaryKey('id_group')]
class GameGroup extends Model
{

	public const TABLE = 'game_groups';

	public string $name   = '';
	public bool   $active = true;

	// TODO: Fix this so that OneToMany connection uses a factory when available
	/** @var Game[] */
	private array $games = [];

	/** @var Player[] */
	private array $players = [];

	/**
	 * @return static[]
	 * @throws ValidationException
	 */
	public static function getActive() : array {
		return static::query()->where('[active] = 1')->get();
	}

	/**
	 * @return Player[]
	 * @throws Throwable
	 */
	public function getPlayers() : array {
		$games = $this->getGames();
		if (empty($games)) {
			return [];
		}
		if (empty($this->players)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			$this->players = $cache->load('group/'.$this->id.'/players', function(array &$dependencies) use ($games) : array {
				$dependencies[CacheParent::Tags] = [
					'gameGroups',
					'group/'.$this->id.'/players'
				];
				$dependencies[CacheParent::EXPIRE] = '1 months';
				$players = [];
				$playerSkills = [];
				$playerVests = [];
				foreach ($games as $game) {
					/** @var Player $player */
					foreach ($game->getPlayers() as $player) {
						$asciiName = Strings::toAscii($player->name);
						if (!isset($players[$asciiName])) {
							$playerSkills[$asciiName] = [];
							$players[$asciiName] = clone $player;
							$playerVests[$asciiName] = [];
						}
						if ($players[$asciiName]->name === $asciiName && $player->name !== $asciiName) {
							$players[$asciiName]->name = $player->name; // Prefer non-ascii (with diacritics) names
						}
						$playerSkills[$asciiName][] = $player->skill;
						if (!isset($playerVests[$asciiName][$player->vest])) {
							$playerVests[$asciiName][$player->vest] = 0;
						}
						$playerVests[$asciiName][$player->vest]++;
					}
				}

				// Set player's skill as average
				foreach ($players as $player) {
					$asciiName = Strings::toAscii($player->name);
					$player->skill = (int) round(array_sum($playerSkills[$asciiName]) / count($playerSkills[$asciiName]));
					// Sort player vests by his use count
					arsort($playerVests[$asciiName]);
					$player->vest = array_key_first($playerVests[$asciiName]);
				}
				return $players;
			});
		}
		/** @phpstan-ignore-next-line */
		return $this->players;
	}

	/**
	 * @return Game[]
	 * @throws Throwable
	 */
	public function getGames() : array {
		if (empty($this->games)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			/** @phpstan-ignore-next-line */
			$this->games = $cache->load('group/'.$this->id.'/games', function(array &$dependencies) : array {
				$dependencies[CacheParent::Tags] = [
					'gameGroups',
					'group/'.$this->id.'/games',
				];
				$dependencies[CacheParent::EXPIRE] = '1 months';
				$games = [];
				$rows = GameFactory::queryGames(true, fields: ['id_group'])->where('[id_group] = %i', $this->id)->cacheTags('group/'.$this->id.'/games')->fetchAll();
				foreach ($rows as $row) {
					$games[] = GameFactory::getByCode($row->code);
				}
				return $games;
			});
		}
		/** @phpstan-ignore-next-line */
		return $this->games;
	}

	public function clearCache() : void {
		parent::clearCache();
		if (isset($this->id)) {
			/** @var Cache $cache */
			$cache = App::getService('cache');
			$cache->clean([
											CacheParent::Tags => [
												'group/'.$this->id.'/games',
												'group/'.$this->id.'/players',
											]
										]);
			$cache->remove('group/'.$this->id.'/players');
			$cache->remove('group/'.$this->id.'/games');
		}
	}

}