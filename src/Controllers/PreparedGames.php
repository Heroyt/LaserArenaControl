<?php

namespace App\Controllers;

use Lsr\Core\Caching\Cache;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Delete;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class PreparedGames extends Controller
{
    public const TABLE = 'prepared_games';

    public const CACHE_TAGS = ['prepared_games'];

    public function __construct(
        private readonly Cache $cache
    ) {
        parent::__construct();
    }

    public function deleteAll(): ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['active = 1']);
        $this->cache->clean([Cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }

    public function save(Request $request): ResponseInterface {
        DB::insert($this::TABLE, ['data' => json_encode($request->getParsedBody(), JSON_THROW_ON_ERROR)]);

        $this->cache->clean([Cache::Tags => $this::CACHE_TAGS]);

        return $this->respond(['status' => 'ok']);
    }

    public function get(Request $request): ResponseInterface {
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
                'id_game' => $row->id_game,
                'datetime' => $row->datetime,
                'data' => json_decode($row->data, false, 512, JSON_THROW_ON_ERROR),
                'active' => (bool)$row->active,
            ];
        }
        return $this->respond($games);
    }

    public function delete(int $id): ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['id_game = %i', $id]);

        $this->cache->clean([Cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }
}
