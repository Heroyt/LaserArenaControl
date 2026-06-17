<?php

namespace App\Http\Controllers;

use App\CQRS\Commands\PrepareGameCommand;
use App\DataObjects\NewGame\GameLoadData;
use App\DataObjects\PreparedGames\PreparedGameDto;
use App\DataObjects\PreparedGames\PreparedGameType;
use Lsr\Caching\Cache;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\CQRS\CommandBus;
use Lsr\Db\DB;
use Lsr\Interfaces\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Serializer;

class PreparedGames extends Controller
{
    public const string TABLE = 'prepared_games';

    public const array CACHE_TAGS = ['prepared_games'];

    public function __construct(
        private readonly Cache            $cache,
        private readonly Serializer       $serializer,
        private readonly SessionInterface $session,
        private readonly CommandBus       $commandBus,
    ) {
    }

    public function deleteAll(): ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['active = 1']);
        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }

    public function save(Request $request, string $type = PreparedGameType::PREPARED->value): ResponseInterface {
        $preparedType = PreparedGameType::tryFrom($type) ?? PreparedGameType::PREPARED;
        $system = $request->getPost('system');
        if ($system === null) {
            $system = $this->session->get('active_lg_system');
        }

        /** @var array<string,mixed> $body */
        $body = $request->getParsedBody();
        if (
            ! $this->commandBus->dispatch(
                new PrepareGameCommand(
                    $preparedType,
                    $system,
                    $body,
                ),
            )
        ) {
            return $this->respond(new ErrorResponse('Failed to save prepared game'), 500);
        }

        return $this->respond(new SuccessResponse());
    }

    public function get(Request $request): ResponseInterface {
        $all = ! empty($request->getGet('all'));

        $query = DB::select($this::TABLE, '*')->cacheTags(...$this::CACHE_TAGS);
        if ( ! $all) {
            $query->where('`active` = 1');
        }
        $query->orderBy('datetime')->desc();

        $games = [];
        $rows = $query->fetchAll();
        foreach ($rows as $row) {
            $games[] = new PreparedGameDto(
                data: $this->serializer->deserialize($row->data, GameLoadData::class, 'json'),
                id: $row->id_game,
                datetime: $row->datetime,
                type: PreparedGameType::tryFrom($row->type) ?? PreparedGameType::PREPARED,
                active: (bool)$row->active,
                id_system: $row->id_system,
            );
        }
        return $this->respond($games);
    }

    public function delete(int $id): ResponseInterface {
        DB::update($this::TABLE, ['active' => 0], ['id_game = %i', $id]);

        $this->cache->clean([$this->cache::Tags => $this::CACHE_TAGS]);
        return $this->respond(['status' => 'ok']);
    }
}
