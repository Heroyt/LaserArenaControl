<?php

namespace App\Controllers;

use App\Models\Auth\Player;
use App\Services\LaserLiga\PlayerProvider;
use App\Services\TaskProducer;
use App\Tasks\GameImportTask;
use InvalidArgumentException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Options;

class Players extends Controller
{
    public function __construct(
        private readonly PlayerProvider $playerProvider,
        private readonly TaskProducer $taskProducer,
    ) {
        parent::__construct();
    }

    public function getPlayer(string $code): ResponseInterface {
        try {
            $player = Player::getByCode($code);
        } catch (InvalidArgumentException $e) {
            return $this->respond(['error' => $e->getMessage(), 'code' => $code], 400);
        }
        if (!isset($player)) {
            $player = $this->playerProvider->findPublicPlayerByCode($code);
        }
        if (!isset($player)) {
            return $this->respond(['error' => 'Player not found'], 404);
        }
        return $this->respond($player);
    }

    public function syncPlayer(string $code): ResponseInterface {
        $player = $this->playerProvider->findPublicPlayerByCode($code);
        if (!isset($player)) {
            return $this->respond(['error' => 'Player not found'], 404);
        }
        if (!$player->save()) {
            return $this->respond(['error' => 'Save failed'], 500);
        }
        return $this->respond($player);
    }

    public function find(Request $request): ResponseInterface {
        return $this->respond(
            array_values(
                $this->playerProvider->findPlayersLocal(
                    (string) $request->getGet('search', ''),
                    empty($request->getGet('nomail', ''))
                )
            )
        );
    }

    public function findPublic(Request $request): ResponseInterface {
        return $this->respond(
            $this->playerProvider->findPlayersPublic(
                (string) $request->getGet('search', '')
            )
        );
    }

    public function sync(): ResponseInterface {
        try {
            $this->taskProducer->push(GameImportTask::class, null, new Options(priority: GameImportTask::PRIORITY));
        } catch (JobsException $e) {
            return $this->respond(new ErrorResponse($e->getMessage(), exception: $e), 500);
        }
        return $this->respond(new SuccessResponse());
    }

    public function show(Request $request): ResponseInterface {
        $perPage = 20;
        $fields = ['nickname', 'code', 'email', 'rank'];
        $sort = $request->getGet('sort', 'nickname');
        if (!in_array($sort, $fields, true)) {
            $sort = 'nickname';
        }
        $desc = !empty($request->getGet('desc'));
        $search = $request->getGet('search', '');
        $query = Player::query();
        $query->orderBy($sort);
        if ($desc) {
            $query->desc();
        }
        $query->orderBy('id_user');
        if (!empty($search)) {
            $query->where(
                '%or',
                [
                ['[code] LIKE %~like~', $search],
                ['[nickname] LIKE %~like~', $search],
                ['[email] LIKE %~like~', $search],
                ]
            );
        }
        $page = (int) $request->getGet('page', 0);
        $query->limit($perPage)->offset($page * $perPage);
        $this->params['players'] = $query->get();
        $this->params['sort'] = $sort;
        $this->params['desc'] = $desc;
        $this->params['search'] = $search;
        $this->params['tablePage'] = $page;
        $this->params['ajax'] = $request->isAjax();
        if ($request->isAjax()) {
            return $this->view('pages/players/table');
        }
        return $this->view('pages/players/list');
    }
}
