<?php

namespace App\Services\GameHighlight;

use App\Core\App;
use App\GameModels\Game\Game;
use App\GameModels\Game\Player;
use App\GameModels\Game\Team;
use App\Models\DataObjects\Highlights\GameHighlight;
use App\Models\DataObjects\Highlights\GameHighlightType;
use App\Models\DataObjects\Highlights\HighlightCollection;
use App\Models\DataObjects\Highlights\HighlightDto;
use App\Services\FeatureConfig;
use App\Services\LaserLiga\LigaApi;
use DateTimeInterface;
use Dibi\DriverException;
use Dibi\Exception;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Lsr\Caching\Cache;
use Lsr\Db\DB;
use Throwable;

class GameHighlightService
{
    public const  string TABLE = 'game_highlights';
    public const string PLAYER_REGEXP = '/@([^@]+)@(?:<([^@]+)>)?/';

    /**
     * @var array<string,array<string,Player|null>>
     * @phpstan-ignore missingType.generics
     */
    private array $playerCache = [];

    /** @var GameHighlightChecker[] */
    private array $gameCheckers = [];
    /** @var PlayerHighlightChecker[] */
    private array $playerCheckers = [];
    /** @var TeamHighlightChecker[] */
    private array $teamCheckers = [];

    /**
     * @param  array<GameHighlightChecker|PlayerHighlightChecker|TeamHighlightChecker>  $checkers
     */
    public function __construct(
      array                          $checkers,
      private readonly Cache         $cache,
      private readonly LigaApi       $api,
      private readonly FeatureConfig $config,
    ) {
        // Distribute checkers
        foreach ($checkers as $checker) {
            if ($checker instanceof GameHighlightChecker) {
                $this->gameCheckers[] = $checker;
            }
            if ($checker instanceof PlayerHighlightChecker) {
                $this->playerCheckers[] = $checker;
            }
            if ($checker instanceof TeamHighlightChecker) {
                $this->teamCheckers[] = $checker;
            }
        }
    }

    /**
     * @param  DateTimeInterface  $date
     * @return HighlightDto[]
     * @phpstan-ignore missingType.generics
     */
    public function getHighlightsDataForDay(DateTimeInterface $date) : array {
        $highlights = [];
        $rows = DB::select(self::TABLE, '*')
                  ->where('DATE([datetime]) = %d AND [object] IS NOT NULL', $date)
                  ->orderBy('rarity')
                  ->desc()
                  ->cacheTags(
                    'highlights',
                    'highlights/'.$date->format('Y-m-d'),
                    'games/'.$date->format('Y-m-d')
                  )
                  ->fetchAll();
        foreach ($rows as $row) {
            try {
                $highlights[] = new HighlightDto(
                  $row->code,
                  $row->datetime,
                  $row->rarity,
                  GameHighlightType::from($row->type),
                  $row->description,
                  isset($row->players) ? json_decode($row->players, true) : null,
                  isset($row->object) ? igbinary_unserialize($row->object) : null,
                );
            } catch (Throwable) {

            }
        }
        return $highlights;
    }

    public function getHighlightsForDay(DateTimeInterface $date) : HighlightCollection {
        /** @var string[] $rows */
        $rows = DB::select(self::TABLE, '[object]')
                  ->where('DATE([datetime]) = %d AND [object] IS NOT NULL', $date)
                  ->orderBy('rarity')
                  ->desc()
                  ->cacheTags(
                    'highlights',
                    'highlights/'.$date->format('Y-m-d'),
                    'games/'.$date->format('Y-m-d')
                  )
                  ->fetchPairs();

        $highlights = new HighlightCollection();
        foreach ($rows as $row) {
            $object = igbinary_unserialize($row);
            if ($object instanceof GameHighlight) {
                $highlights->add($object);
            }
        }
        return $highlights;
    }

    /**
     * Get all highlight for a game
     *
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  G  $game
     * @param  bool  $cache
     *
     * @return HighlightCollection
     * @throws Throwable Cache error
     */
    public function getHighlightsForGame(Game $game, bool $cache = true) : HighlightCollection {
        $dependencies = [
          'tags' => $this->getCacheTags($game),
        ];
        if (!$cache) {
            $highlights = $this->loadHighlightsForGame($game);
            // Cache result
            $this->cache->save(
              'game.'.$game->code.'.highlights.'.App::getShortLanguageCode(),
              $highlights,
              $dependencies,
            );
            return $highlights;
        }

        return $this->cache->load(
          'game.'.$game->code.'.highlights.'.App::getShortLanguageCode(),
          fn() => $this->loadHighlightsForGame($game),
          $dependencies,
        );
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G  $game
     * @return non-empty-string[]
     */
    private function getCacheTags(Game $game) : array {
        return [
          'highlights',
          'highlights/'.$game->start?->format('Y-m-d'),
          'games',
          'games/'.$game::SYSTEM,
          'games/'.$game::SYSTEM.'/'.$game->id,
          'games/'.$game->code,
          'games/'.$game->start?->format('Y-m-d'),
          'games/'.$game->code.'/highlights',
        ];
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  G  $game
     * @param  bool  $generate
     * @return HighlightCollection
     * @throws GuzzleException
     */
    private function loadHighlightsForGame(Game $game, bool $generate = false) : HighlightCollection {
        $ligaActive = $this->config->isFeatureEnabled('LIGA') && $game->sync;
        $highlights = null;
        if ($generate) {
            if ($ligaActive) {
                $highlights = $this->getHighlightsFromLiga($game);
            }
            $highlights ??= $this->generateHighlightsForGame($game);
            $this->saveHighlightCollection($highlights, $game);
            return $highlights;
        }

        $highlights = $this->loadHighlightsForGameFromDb($game);
        if ($ligaActive && $highlights->count() === 0) {
            $highlights = $this->getHighlightsFromLiga($game);
            $this->saveHighlightCollection($highlights, $game);
        }
        if ($highlights->count() === 0) {
            $highlights = $this->generateHighlightsForGame($game);
            $this->saveHighlightCollection($highlights, $game);
        }

        return $highlights;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G  $game
     * @return HighlightCollection
     * @throws GuzzleException
     * @throws JsonException
     */
    private function getHighlightsFromLiga(Game $game) : HighlightCollection {
        $response = $this->api->get('/api/games/'.$game->code.'/highlights');
        $response->getBody()->rewind();
        $contents = $response->getBody()->getContents();

        $collection = new HighlightCollection();

        if ($response->getStatusCode() !== 200) {
            // Return empty collection on error
            return $collection;
        }

        /** @var array{type:string,score:int,value:string,description:string}[] $highlights */
        $highlights = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        foreach ($highlights as $highlight) {
            $collection->add(
              (GameHighlightType::from($highlight['type'])->getHighlightClass()::fromJson($highlight, $game))
            );
        }
        return $collection;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     * @param  G  $game
     * @return HighlightCollection
     */
    private function generateHighlightsForGame(Game $game) : HighlightCollection {
        $highlights = new HighlightCollection();

        foreach ($game->teams as $team) {
            foreach ($this->teamCheckers as $checker) {
                $checker->checkTeam($team, $highlights);
            }
        }

        foreach ($game->players as $player) {
            foreach ($this->playerCheckers as $checker) {
                $checker->checkPlayer($player, $highlights);
            }
        }

        foreach ($this->gameCheckers as $checker) {
            $checker->checkGame($game, $highlights);
        }

        return $highlights;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  HighlightCollection  $collection
     * @param  G  $game
     * @return bool
     * @throws DriverException
     * @throws JsonException
     */
    private function saveHighlightCollection(HighlightCollection $collection, Game $game) : bool {
        assert($game->isFinished());
        try {
            DB::getConnection()->begin();
            foreach ($collection->getAll() as $highlight) {
                DB::replace(
                  $this::TABLE,
                  [
                    'code'        => $game->code,
                    'datetime'    => $game->start,
                    'rarity'      => $highlight->rarityScore,
                    'type'        => $highlight->type->value,
                    'description' => $highlight->getDescription(),
                    'players'     => json_encode(
                      $this->getHighlightPlayers($highlight, $game),
                      JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'object'      => igbinary_serialize($highlight),
                  ]
                );
            }
            DB::getConnection()->commit();
        } catch (Exception) {
            DB::getConnection()->rollback();
            return false;
        }

        $this->cache->clean(
          [
            $this->cache::Tags => [
              'games/'.$game->code.'/highlights',
              'highlights/'.$game->start->format('d-m-Y'),
            ],
          ]
        );

        return true;
    }

    /**
     * Get players from highlight
     *
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  GameHighlight  $highlight
     * @param  G  $game
     *
     * @return array{name:string,label:string,user:string|null}[]
     */
    public function getHighlightPlayers(GameHighlight $highlight, Game $game) : array {
        preg_match_all($this::PLAYER_REGEXP, $highlight->getDescription(), $matches, PREG_SET_ORDER);
        $players = [];
        foreach ($matches as $match) {
            $name = $match[1];
            $label = $match[2] ?? $name;
            $player = $this->getPlayerByName($name, $game);
            $players[] = ['name' => $name, 'label' => $label, 'user' => $player?->user?->getCode()];
        }
        return $players;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  string  $name
     * @param  G  $game
     *
     * @return P|null
     */
    private function getPlayerByName(string $name, Game $game) : ?Player {
        $this->playerCache[$game->code] ??= [];
        if (array_key_exists($name, $this->playerCache[$game->code])) { // Might be null, which is valid.
            /** @phpstan-ignore return.type */
            return $this->playerCache[$game->code][$name];
        }
        $this->playerCache[$game->code][$name] = $game->players->query()->filter('name', $name)->first();
        return $this->playerCache[$game->code][$name];
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  G  $game
     *
     * @return HighlightCollection
     */
    private function loadHighlightsForGameFromDb(Game $game) : HighlightCollection {
        $highlights = new HighlightCollection();
        /** @var string[] $objects */
        $objects = DB::select($this::TABLE, '[object]')
                     ->where('[code] = %s && [object] IS NOT NULL', $game->code)
          ->cacheTags(...$this->getCacheTags($game))
          ->fetchPairs();

        foreach ($objects as $object) {
            $highlight = @igbinary_unserialize($object);
            if ($highlight instanceof GameHighlight) {
                $highlights->add($highlight);
            }
        }
        return $highlights;
    }

    /**
     * @template T of Team
     * @template P of Player
     * @template G of Game<T,P>
     *
     * @param  string  $highlightDescription
     * @param  G  $game
     *
     * @return string
     */
    public function playerNamesToLinks(string $highlightDescription, Game $game) : string {
        $replaced = preg_replace_callback(
          $this::PLAYER_REGEXP,
          function (array $matches) use ($game) {
              $playerName = $matches[1];
              $label = $matches[2] ?? $playerName;

              $player = $this->getPlayerByName($playerName, $game);
              if (!isset($player)) {
                  return $label;
              }
              return '<a href="#player-'.str_replace(' ', '_', $playerName).'" '.
                'class="player-link" '.
                'data-user="'.$player->user?->getCode().'" '.
                'data-name="'.$playerName.'"  '.
                'data-vest="'.$player->vest.'">'.$label.'</a>';
          },
          $highlightDescription
        );
        return $replaced ?? $highlightDescription;
    }
}
