<?php

namespace App\Controllers;

use App\Models\DataObjects\NewGame\GameLoadData;
use App\Models\DataObjects\PreparedGames\PreparedGameDto;
use App\Models\DataObjects\PreparedGames\PreparedGameType;
use App\Models\System;
use App\Models\SystemType;
use Lsr\Caching\Cache;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Db\DB;
use Lsr\Interfaces\SessionInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Serializer;

class PreparedGames extends Controller
{
    public const string TABLE = 'prepared_games';

    public const array CACHE_TAGS = ['prepared_games'];

    public function __construct(
      private readonly Cache      $cache,
      private readonly Serializer $serializer,
      private readonly SessionInterface $session,
    ) {}

    public function deleteAll() : ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['active = 1']);
        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }

    public function save(Request $request, string $type = PreparedGameType::PREPARED->value) : ResponseInterface {
        $preparedType = PreparedGameType::tryFrom($type) ?? PreparedGameType::PREPARED;
        $system = $request->getPost('system');
        if ($system === null) {
            $system = $this->session->get('active_lg_system');
        }
        if (is_numeric($system)) {
            try {
                $system = System::get((int) $system);
            } catch (ModelNotFoundException) {
                $system = null;
            }
        }
        else if (is_string($system)) {
            $type = SystemType::tryFrom($system);
            if ($type !== null) {
                $systems = System::getForType($type);
                $system = first($systems);
            }
            else {
                $system = null;
            }
        }
        DB::insert(
          $this::TABLE,
          [
            'data' => $this->serializer->serialize($request->getParsedBody(), 'json'),
            'type' => $preparedType->value,
            'id_system' => $system->id,
          ]
        );

        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);

        return $this->respond(new SuccessResponse());
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
            $games[] = new PreparedGameDto(
              data     : $this->serializer->deserialize($row->data, GameLoadData::class, 'json'),
              id       : $row->id_game,
              datetime : $row->datetime,
              type     : PreparedGameType::tryFrom($row->type) ?? PreparedGameType::PREPARED,
              active   : (bool) $row->active,
              id_system: $row->id_system,
            );
        }
        return $this->respond($games);
    }

    public function delete(int $id) : ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['id_game = %i', $id]);

        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }
}
