<?php

namespace App\Controllers;

use App\Models\GameGroup;
use JsonException;
use Lsr\Core\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Routing\Attributes\Update;
use Lsr\Logging\Exceptions\DirectoryCreationException;

/**
 *
 */
class GameGroups extends Controller
{

	#[Get('/gameGroups')]
	public function listGroups() : never {
		$groups = isset($_GET['all']) ? GameGroup::getAll() : GameGroup::getActive();
		$data = [];
		foreach ($groups as $group) {
			$groupData = $group->jsonSerialize();
			$groupData['players'] = $group->getPlayers();
			$data[] = $groupData;
		}
		$this->respond($data);
	}

	#[Get('/gameGroups/{id}')]
	public function getGroup(Request $request) : never {
		try {
			$group = GameGroup::get((int) ($request->params['id'] ?? 0));
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Model not found', 'exception' => $e->getMessage()], 404);
		}
		$groupData = $group->jsonSerialize();
		$groupData['players'] = $group->getPlayers();
		$this->respond($groupData);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/gameGroups')]
	public function create(Request $request) : never {
		$group = new GameGroup();
		$group->name = $request->post['name'] ?? sprintf(lang('Skupina %s'), date('d.m.Y H:i'));
		try {
			if (!$group->save()) {
				$this->respond(['error' => 'Save failed'], 500);
			}
		} catch (ValidationException $e) {
			$this->respond(['error' => 'Validation failed', 'exception' => $e->getMessage()], 400);
		}
		$this->respond(['status' => 'ok', 'id' => $group->id]);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Update('/gameGroups/{id}')]
	#[Post('/gameGroups/{id}')]
	public function update(Request $request) : never {
		$id = (int) ($request->params['id'] ?? 0);
		if ($id < 1) {
			$this->respond(['error' => 'Invalid id'], 400);
		}
		try {
			$group = GameGroup::get($id);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			$this->respond(['error' => 'Game not found', 'exception' => $e->getMessage(), 'trace' => $e->getTrace()], 404);
		}

		if (!empty($request->post['name'])) {
			$group->name = $request->post['name'];
		}
		if (isset($request->post['active'])) {
			$group->active = (is_bool($request->post['active']) && $request->post['active'] === true) ||
				(is_numeric($request->post['active']) && ((int) $request->post['active']) === 1) ||
				$request->post['active'] === 'true';
		}
		try {
			if (!$group->save()) {
				$this->respond(['error' => 'Save failed'], 500);
			}
		} catch (ValidationException $e) {
			$this->respond(['error' => 'Validation failed', 'exception' => $e->getMessage()], 400);
		}
		$this->respond(['status' => 'ok', 'id' => $group->id]);
	}

}