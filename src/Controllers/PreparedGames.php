<?php

namespace App\Controllers;

use App\Models\DataObjects\NewGame\GameLoadData;
use App\Models\DataObjects\PreparedGames\PreparedGameType;
use Lsr\Caching\Cache;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Serializer;

class PreparedGames extends Controller
{
    public const string TABLE = 'prepared_games';

    public const array CACHE_TAGS = ['prepared_games'];

    public function __construct(
      private readonly Cache      $cache,
      private readonly Serializer $serializer,
    ) {
        parent::__construct();
    }

    public function deleteAll() : ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['active = 1']);
        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }

    public function save(Request $request, string $type = PreparedGameType::PREPARED->value) : ResponseInterface {
        $preparedType = PreparedGameType::tryFrom($type) ?? PreparedGameType::PREPARED;
        DB::insert(
          $this::TABLE,
          [
            'data' => $this->serializer->serialize($request->getParsedBody(), 'json'),
            'type' => $preparedType->value,
          ]
        );

        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);

        return $this->respond(['status' => 'ok']);
    }

    public function get(Request $request) : ResponseInterface {
        $all = !empty($request->getGet('all'));

        $query = DB::select($this::TABLE, '*')->cacheTags(...$this::CACHE_TAGS);
        if (!$all) {
            $query->where('`active` = 1');
        }
        $query->orderBy('datetime')->desc();

        $games = [];
        $rows = $query->fetchAll();
        foreach ($rows as $row) {
            $games[] = [
              'id_game'  => $row->id_game,
              'datetime' => $row->datetime,
              'data'     => $this->serializer->deserialize($row->data, GameLoadData::class, 'json'),
              'type'     => PreparedGameType::tryFrom($row->type) ?? PreparedGameType::PREPARED,
              'active'   => (bool) $row->active,
            ];
        }
        return $this->respond($games);
    }

    public function delete(int $id) : ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['id_game = %i', $id]);

        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }
}
