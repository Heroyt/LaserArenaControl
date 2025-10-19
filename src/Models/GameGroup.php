<?php

namespace App\Models;

use App\Core\App;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\Models\Group\Player as GroupPlayer;
use App\Models\Group\PlayerPayInfoDto;
use App\Models\Group\Team;
use DateTimeImmutable;
use DateTimeInterface;
use Lsr\Caching\Cache;
use Lsr\Helpers\Tools\Strings;
use Lsr\Lg\Results\Interface\Models\GameGroupInterface;
use Lsr\Lg\Results\Interface\Models\GroupPlayerInterface;
use Lsr\Lg\Results\Interface\Models\PlayerInterface;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Nette\Caching\Cache as CacheParent;
use Throwable;

/**
 * @phpstan-type GroupMeta array{payment: array<string, PlayerPayInfoDto>}
 *
 * @use WithMetaData<GroupMeta>
 */
#[PrimaryKey('id_group')]
class GameGroup extends BaseModel implements GameGroupInterface
{
    /** @phpstan-use WithMetaData<GroupMeta> */
    use WithMetaData;

    public const string TABLE = 'game_groups';

    public string $name = '';
    public bool $active = true;
    public ?DateTimeInterface $createdAt = null;

    /**
     * @var Game[]
     */
    #[NoDB, JsonExclude]
    public array $games = [] { // @phpstan-ignore missingType.generics
        get {
            if (empty($this->games)) {
                /** @var Cache $cache */
                $cache = App::getService('cache');
                $dependencies = [
                  CacheParent::Tags   => ['gameGroups', 'group/'.$this->id.'/games'],
                  CacheParent::Expire => '1 months',
                ];
                try {
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
            return $this->games;
        }
    }

    /** @var GroupPlayer[] */
    #[NoDB, JsonExclude]
    public array $players = [] {
        get {
            if (!empty($this->players)) {
                return $this->players;
            }
            $games = $this->games;
            if (empty($games)) {
                return [];
            }

            /** @var Cache $cache */
            $cache = App::getService('cache');
            $dependencies = [
              CacheParent::Tags   => ['gameGroups', 'group/'.$this->id.'/players'],
              CacheParent::Expire => '1 months',
            ];
            try {
                [$this->players, $this->teams] = $cache->load(
                  'group/'.$this->id.'/players',
                  function () use ($games) : array {
                      return $this->loadPlayersAndTeams($games);
                  },
                  $dependencies,
                );
            } catch (Throwable $e) {
                $this->getLogger()->exception($e);
                $teamsAndPlayers = $this->loadPlayersAndTeams($games);
                $cache->save('group/'.$this->id.'/players', $teamsAndPlayers, $dependencies);
                [$this->players, $this->teams] = $teamsAndPlayers;
            }
            return $this->players;
        }
    }
    /** @var array<string, Team> */
    #[NoDB, JsonExclude]
    public array $teams = [] {
        get {
            if (empty($this->teams) && empty($this->players)) {
                return [];
            }
            return $this->teams;
        }
    }

    /**
     * @return static[]
     */
    public static function getActive() : array {
        return static::query()->where('[active] = 1')->get();
    }

    /**
     * @return static[]
     */
    public static function getActiveByDate(bool $descending = true) : array {
        $query = static::query()
                       ->where('[active] = 1')
                       ->orderBy('[created_at]');
        if ($descending) {
            $query->desc();
        }
        $query->orderBy('[id_group]');
        if ($descending) {
            $query->desc();
        }
        return $query->get();
    }

    /**
     * @return static[]
     */
    public static function getAllByDate(bool $descending = true) : array {
        $query = static::query()
                       ->orderBy('[created_at]');
        if ($descending) {
            $query->desc();
        }
        $query->orderBy('[id_group]');
        if ($descending) {
            $query->desc();
        }
        return $query->get();
    }

    public function save() : bool {
        $this->createdAt ??= new DateTimeImmutable();
        return parent::save();
    }

    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        $data['meta'] = $this->getMeta();
        return $data;
    }


    /**
     * @return Game[]
     * @throws Throwable
     * @phpstan-ignore missingType.generics
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
     * @template T of \App\GameModels\Game\Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G[]  $games
     * @return array{0:array<string,GroupPlayer>,1:array<string,Team>}
     */
    public function loadPlayersAndTeams(array $games) : array {
        $players = [];
        $teams = [];
        foreach ($games as $game) {
            foreach ($game->teams as $team) {
                $tPlayers = [];
                $tPlayerNames = [];
                foreach ($team->players as $player) {
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
                if (!isset($teams[$id])) {
                    $teams[$id] = new Team($id, $team->name, $team::SYSTEM);
                }
                $teams[$id]->name = $team->name;
                $teams[$id]->addColor($team->color);
                $teams[$id]->addPlayer(...array_values($tPlayers));
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

        return [$players, $teams];
    }

    /**
     * @return GroupPlayer[]
     * @throws Throwable
     */
    public function getPlayersSortedByName() : array {
        $players = $this->players;
        uasort(
          $players,
          static fn(GroupPlayer $a, GroupPlayer $b) => strcmp(strtolower($a->name), strtolower($b->name))
        );
        return $players;
    }

    #[AfterUpdate, AfterInsert, AfterDelete]
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

    public function getPlayer(PlayerInterface $player) : ?GroupPlayerInterface {
        $name = $player->name;
        return $this->getPlayerByName($name);
    }

    public function getPlayerByName(string $name) : ?GroupPlayerInterface {
        $name = Strings::toAscii($name);
        return array_find($this->players, static fn(GroupPlayer $player) => $player->asciiName === $name);
    }

    public function getGamesCodes() : array {
        return array_map(static fn(Game $game) => $game->code, $this->games);
    }

    public function getDateRange(string $format = 'd.m.Y') : string {
        return '';
    }
}
