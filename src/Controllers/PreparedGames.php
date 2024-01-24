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

class PreparedGames extends Controller
{

	public const TABLE = 'prepared_games';

	public const CACHE_TAGS = ['prepared_games'];

	public function __construct(
		Latte                  $latte,
		private readonly Cache $cache
	) {
		parent::__construct($latte);
	}

	#[Delete('/prepared'), Post('/prepared/delete')]
	public function deleteAll(): never {
		DB::update($this::TABLE, ['active' => 0], ['active = 1']);
		$this->cache->clean([Cache::Tags => $this::CACHE_TAGS]);
		$this->respond(['status' => 'ok']);
	}

	#[Post('/prepared')]
	public function save(Request $request): never {
		DB::insert($this::TABLE, ['data' => json_encode($request->post, JSON_THROW_ON_ERROR)]);

		$this->cache->clean([Cache::Tags => $this::CACHE_TAGS]);

		$this->respond(['status' => 'ok']);
	}

	#[Get('/prepared')]
	public function get(Request $request): never {
		$all = !empty($request->get['all']);

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
		$this->respond($games);
	}

	#[Post('/prepared/{id}/delete'), Delete('/prepared/{id}')]
	public function delete(int $id): never {
		DB::update($this::TABLE, ['active' => 0], ['id_game = %i', $id]);

		$this->cache->clean([Cache::Tags => $this::CACHE_TAGS]);
		$this->respond(['status' => 'ok']);
	}

}