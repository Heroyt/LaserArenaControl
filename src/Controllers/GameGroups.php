<?php

namespace App\Controllers;

use App\Models\GameGroup;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Core\Routing\Attributes\Update;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class GameGroups extends Controller
{

	#[Get('/gameGroups')]
	public function listGroups(): ResponseInterface {
		$groups = isset($_GET['all']) ? GameGroup::getAll() : GameGroup::getActive();
		$data = [];
		foreach ($groups as $group) {
			$groupData = $group->jsonSerialize();
			$groupData['players'] = $group->getPlayers();
			$groupData['teams'] = $group->getTeams();
			$data[] = $groupData;
		}
		return $this->respond($data);
	}

	#[Get('/gameGroups/{id}')]
	public function getGroup(Request $request): ResponseInterface {
		try {
			$group = GameGroup::get((int) ($request->params['id'] ?? 0));
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			return $this->respond(['error' => 'Model not found', 'exception' => $e->getMessage()], 404);
		}
		$groupData = $group->jsonSerialize();
		$groupData['players'] = $group->getPlayers();
		return $this->respond($groupData);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('/gameGroups')]
	public function create(Request $request): ResponseInterface {
		$group = new GameGroup();
		$group->name = $request->getPost('name', sprintf(lang('Skupina %s'), date('d.m.Y H:i')));
		try {
			if (!$group->save()) {
				return $this->respond(['error' => 'Save failed'], 500);
			}
		} catch (ValidationException $e) {
			return $this->respond(['error' => 'Validation failed', 'exception' => $e->getMessage()], 400);
		}
		return $this->respond(['status' => 'ok', 'id' => $group->id]);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Update('/gameGroups/{id}')]
	#[Post('/gameGroups/{id}')]
	public function update(Request $request): ResponseInterface {
		$id = (int) ($request->params['id'] ?? 0);
		if ($id < 1) {
			return $this->respond(['error' => 'Invalid id'], 400);
		}
		try {
			$group = GameGroup::get($id);
		} catch (ModelNotFoundException|ValidationException|DirectoryCreationException $e) {
			return $this->respond(
				['error' => 'Group not found', 'exception' => $e->getMessage(), 'trace' => $e->getTrace()],
				404
			);
		}

		$name = $request->getPost('name', '');
		if (!empty($name)) {
			$group->name = $name;
		}
		$active = $request->getPost('active');
		if ($active !== null) {
			$group->active = (is_bool($active) && $active) ||
				(is_numeric($active) && ((int)$active) === 1) ||
				$active === 'true';
		}
		try {
			if (!$group->save()) {
				return $this->respond(['error' => 'Save failed'], 500);
			}
		} catch (ValidationException $e) {
			return $this->respond(['error' => 'Validation failed', 'exception' => $e->getMessage()], 400);
		}
		return $this->respond(['status' => 'ok', 'id' => $group->id]);
	}

}