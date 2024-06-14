<?php

namespace App\Models;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Group\Player as GroupPlayer;
use App\Models\Group\Team;
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

    public const string TABLE = 'game_groups';

    public string $name = '';
    public bool $active = true;

    // TODO: Fix this so that OneToMany connection uses a factory when available
    /** @var Game[] */
    private array $games = [];

    /** @var GroupPlayer[] */
    private array $players = [];
    /** @var array<string, Team> */
    private array $teams = [];

    /**
     * @return static[]
     * @throws ValidationException
     */
    public static function getActive() : array {
        return static::query()->where('[active] = 1')->get();
    }

    /**
     * @post Games are loaded
     * @post Teams are loaded
     * @post Players are loaded
     *
     * @return array<string, Team>
     * @throws Throwable
     */
    public function getTeams() : array {
        if (empty($this->teams) && empty($this->getPlayers())) {
            return [];
        }
        return $this->teams;
    }

    /**
     * @post Games are loaded
     * @post Teams are loaded
     * @post Players are loaded
     *
     * @return GroupPlayer[]
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
            $dependencies = [
              CacheParent::Tags   => ['gameGroups', 'group/'.$this->id.'/players'],
              CacheParent::Expire => '1 months',
            ];
            try {
                // @phpstan-ignore-next-line
                $this->players = $cache->load(
                  'group/'.$this->id.'/players',
                  function () use ($games) : array {
                      return $this->loadPlayers($games);
                  },
                  $dependencies,
                );
            } catch (Throwable $e) {
                $this->getLogger()->exception($e);
                $this->players = $this->loadPlayers($games);
                $cache->save('group/'.$this->id.'/players', $this->players, $dependencies);
            }
        }

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
            $dependencies = [
              CacheParent::Tags   => ['gameGroups', 'group/'.$this->id.'/games'],
              CacheParent::Expire => '1 months',
            ];
            try {
                /** @phpstan-ignore-next-line */
                $this->games = $cache->load(
                  'group/'.$this->id.'/games',
                  [$this, 'loadGames'],
                  $dependencies
                );
            } catch (Throwable $e) {
                $this->getLogger()->exception($e);
                $this->games = $this->loadGames();
                $cache->save('group/'.$this->id.'/games', $this->games, $dependencies);
            }
        }
        /** @phpstan-ignore-next-line */
        return $this->games;
    }

    /**
     * @return Game[]
     * @throws Throwable
     */
    public function loadGames() : array {
        $games = [];
        $rows = GameFactory::queryGames(true, fields: ['id_group'])
                           ->where('[id_group] = %i', $this->id)
                           ->cacheTags('group/'.$this->id.'/games')
                           ->fetchAll();
        foreach ($rows as $row) {
            $games[] = GameFactory::getByCode($row->code);
        }
        return $games;
    }

    /**
     * @param  Game[]  $games
     * @return Player[]
     */
    public function loadPlayers(array $games) : array {
        $players = [];
        foreach ($games as $game) {
            /** @var \App\GameModels\Game\Team[] $team */
            foreach ($game->getTeams() as $team) {
                $tPlayers = [];
                $tPlayerNames = [];
                /** @var Player $player */
                foreach ($team->getPlayers() as $player) {
                    $asciiName = Strings::toAscii($player->name);
                    $tPlayerNames[] = $asciiName;
                    if (!isset($players[$asciiName])) {
                        $players[$asciiName] = new GroupPlayer(
                          $asciiName,
                          clone $player
                        );
                        $players[$asciiName]->name = $player->name;
                    }

                    // Add player to the team
                    if (!isset($tPlayers[$asciiName])) {
                        $tPlayers[$asciiName] = $players[$asciiName];
                    }

                    // Add game to the player
                    $players[$asciiName]->addGame($player, $game);
                }
                sort($tPlayerNames);
                $id = md5(implode('', $tPlayerNames));
                if (!isset($this->teams[$id])) {
                    $this->teams[$id] = new Team($id, $team->name, $team::SYSTEM);
                }
                $this->teams[$id]->name = $team->name;
                $this->teams[$id]->addColor($team->color);
                $this->teams[$id]->addPlayer(...array_values($tPlayers));
            }
        }

        // Copy some values to the base Player class
        foreach ($players as $player) {
            $player->player->skill = $player->getSkill();
            $player->player->vest = $player->getFavouriteVest();
        }

        // Sort players by their skill in descending order
        uasort(
          $players,
          static fn(GroupPlayer $playerA, GroupPlayer $playerB) => $playerB->getSkill() - $playerA->getSkill()
        );

        return $players;
    }

    public function clearCache() : void {
        parent::clearCache();
        if (isset($this->id)) {
            /** @var Cache $cache */
            $cache = App::getService('cache');
            $cache->clean(
              [
                CacheParent::Tags => [
                  'group/'.$this->id.'/games',
                  'group/'.$this->id.'/players',
                ],
              ]
            );
            $cache->remove('group/'.$this->id.'/players');
            $cache->remove('group/'.$this->id.'/games');
            $cache->remove('group/'.$this->id.'/games/ids');
        }
    }

}