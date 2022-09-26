<?php

namespace App\Controllers;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Game\Enums\GameModeType;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Game\GameModes\CustomSoloMode;
use App\GameModels\Game\GameModes\CustomTeamMode;
use App\Models\GameModeVariation;
use Dibi\DriverException;
use Exception;
use JsonException;
use Lsr\Core\App;
use Lsr\Core\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Delete;
use Lsr\Core\Routing\Attributes\Get;
use Lsr\Core\Routing\Attributes\Post;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Logging\Exceptions\DirectoryCreationException;
use RuntimeException;
use Throwable;

/**
 *
 */
class ModesSettings extends Controller
{

	/**
	 * @param Request $request
	 *
	 * @return void
	 * @throws GameModeNotFoundException
	 * @throws TemplateDoesNotExistException
	 */
	#[Get('settings/modes', 'settings-modes')]
	#[Get('settings/modes/{system}')]
	public function modes(Request $request) : void {
		$this->params['system'] = $request->params['system'] ?? first(GameFactory::getSupportedSystems());
		$this->params['modes'] = GameModeFactory::getAll(['system' => $this->params['system']]);
		$this->view('pages/settings/modes');
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws ModelNotFoundException
	 * @throws ValidationException
	 * @throws DirectoryCreationException
	 */
	#[Get('settings/modes/{id}/variations')]
	public function modeVariations(Request $request) : never {
		$id = $this->getRequestId($request);

		try {
			$mode = GameModeFactory::getById($id);
			if (!isset($mode)) {
				throw new GameModeNotFoundException();
			}
		} catch (GameModeNotFoundException $e) {
			$this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
		}

		$variations = [];
		foreach ($mode->getVariations() as $variationId => $values) {
			$variations[$variationId] = [
				'variation' => $values[0]->variation,
				'values'    => $values,
			];
		}

		$this->respond([
										 'mode'       => $mode,
										 'variations' => $variations,
									 ]);
	}

	/**
	 * @param Request $request
	 *
	 * @return int
	 * @throws JsonException
	 */
	protected function getRequestId(Request $request) : int {
		$id = (int) ($request->params['id'] ?? 0);
		if ($id <= 0) {
			$this->respond(['error' => 'Invalid parameter id'], 400);
		}
		return $id;
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Get('settings/modes/{id}/settings')]
	public function modeSettings(Request $request) : never {
		$id = $this->getRequestId($request);

		try {
			$mode = GameModeFactory::getById($id);
			if (!isset($mode)) {
				throw new GameModeNotFoundException();
			}
		} catch (GameModeNotFoundException $e) {
			$this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
		}

		$this->respond($mode->settings);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Get('settings/modes/{id}/names')]
	public function modeNames(Request $request) : never {
		$id = $this->getRequestId($request);

		try {
			$mode = GameModeFactory::getById($id);
			if (!isset($mode)) {
				throw new GameModeNotFoundException();
			}
		} catch (GameModeNotFoundException $e) {
			$this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
		}

		$names = DB::select('[game_modes-names]', 'sysName')->where('id_mode = %i', $id)->fetchPairs();
		$this->respond($names);
	}

	#[Post('/settings/modes/{id}/names')]
	public function saveModeNames(Request $request) : never {
		$id = $this->getRequestId($request);

		/** @var string[] $names */
		$names = $request->post['modeNames'] ?? [];

		try {
			DB::getConnection()->begin();
			DB::delete('game_modes-names', ['id_mode = %i', $id]);
			foreach ($names as $name) {
				if ($name === '') {
					continue;
				}
				DB::insert('game_modes-names', ['id_mode' => $id, 'sysName' => $name]);
			}
			DB::getConnection()->commit();
		} catch (DriverException|\Dibi\Exception $e) {
			DB::getConnection()->rollback();
			$this->respond(['error' => 'Failed to save the data to database', 'exception' => $e->getMessage()], 500);
		}
		$this->respond(['status' => 'ok']);
	}

	/**
	 * @return never
	 * @throws JsonException
	 * @throws ValidationException
	 */
	#[Get('/settings/modes/variations')]
	public function getAllVariations() : never {
		$this->respond(GameModeVariation::getAll());
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 * @throws ValidationException
	 */
	#[Post('/settings/modes/variations')]
	public function createVariation(Request $request) : never {
		$name = $request->post['name'] ?? '';
		if (empty($name)) {
			$this->respond(['error' => lang('Název nesmí být prázdný', context: 'errors')], 400);
		}

		$name = trim($name);

		// Check duplicate names
		$test = GameModeVariation::query()->where('[name] = %s', $name)->first();
		if (isset($test)) {
			$this->respond($test);
		}

		$variation = new GameModeVariation();
		$variation->name = $name;
		if ($variation->save()) {
			$this->respond($variation);
		}
		$this->respond(['error' => lang('Nepodařilo se vytvořit variaci', context: 'errors')], 500);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Post('settings/modes')]
	public function save(Request $request) : never {
		$modes = [];
		foreach ($request->post['mode'] ?? [] as $id => $values) {
			bdump($values);
			try {
				$mode = GameModeFactory::getById($id);
				if (!isset($mode)) {
					throw new GameModeNotFoundException();
				}
			} catch (GameModeNotFoundException) {
				continue;
			}
			if (($mode instanceof CustomTeamMode || $mode instanceof CustomSoloMode)) {
				if (isset($values['name'])) {
					$mode->name = $values['name'];
				}
				if (isset($values['type'])) {
					$mode->type = GameModeType::tryFrom($values['type']) ?? $mode->type;
				}
			}
			if (isset($values['load'])) {
				$mode->loadName = $values['load'];
			}
			if (isset($values['description'])) {
				$mode->description = $values['description'];
			}
			if (!empty($values['settings'])) {
				foreach (get_object_vars($mode->settings) as $var => $val) {
					$mode->settings->$var = isset($values['settings'][$var]);
				}
			}
			if (!empty($values['teams'])) {
				$mode->teams = json_encode($values['teams'], JSON_THROW_ON_ERROR);
			}
			try {
				if (!$mode->save()) {
					$request->passErrors[] = lang('Nepodařilo se uložit herní mód', context: 'errors').': '.$mode->name;
					continue;
				}
				$modes[$mode->id] = $mode;
			} catch (ValidationException $e) {
				$request->passErrors[] = lang('Validace selhala', context: 'errors').' - '.$e->getMessage();
			}
		}
		$this->saveResponse($request, ['modes' => $modes]);
	}

	/**
	 * @param Request              $request
	 * @param array<string, mixed> $additional
	 *
	 * @return never
	 * @throws JsonException
	 */
	private function saveResponse(Request $request, array $additional = []) : never {
		if ($request->isAjax()) {
			if (!empty($request->passErrors)) {
				$this->respond(array_merge($additional, ['errors' => $request->passErrors, 'notices' => $request->passNotices]), 500);
			}
			$this->respond(array_merge($additional, ['status' => 'ok', 'notices' => $request->passNotices]));
		}
		App::redirect(['settings', 'modes'], $request);
	}

	#[Post('settings/modes/{id}/variations')]
	public function saveModeVariations(Request $request) : never {
		bdump($request->post);
		$id = (int) ($request->params['id'] ?? 0);
		if ($id <= 0) {
			$request->passErrors[] = 'Invalid parameter id';
			$this->saveResponse($request);
		}

		try {
			$mode = GameModeFactory::getById($id);
			if (!isset($mode)) {
				throw new GameModeNotFoundException();
			}
		} catch (GameModeNotFoundException $e) {
			$request->passErrors[] = 'Game mode not found';
			$this->saveResponse($request);
		}

		/** @var array{name:string,values:array{value?:string,suffix?:string,order?:int}[]}[] $variations */
		$variations = $request->post['variation'] ?? [];

		try {
			DB::getConnection()->begin();
			DB::delete(GameModeVariation::TABLE_VALUES, ['[id_mode] = %i', $id]);
			foreach ($variations as $variationId => $info) {
				$variation = GameModeVariation::get($variationId);
				$variation->name = $info['name'];
				if (!$variation->save()) {
					throw new RuntimeException('Cannot save variation');
				}
				foreach ($info['values'] ?? [] as $value) {
					DB::insert(GameModeVariation::TABLE_VALUES, [
						'id_variation' => $variationId,
						'id_mode'      => $id,
						'value'        => $value['value'] ?? '',
						'suffix'       => $value['suffix'] ?? '',
						'order'        => $value['order'] ?? 0,
					]);
				}
			}
			// Delete empty variations
			DB::delete(GameModeVariation::TABLE, [
				'[id_variation] NOT IN %sql',
				DB::select(GameModeVariation::TABLE_VALUES, 'id_variation')
			]);
			DB::getConnection()->commit();
		} catch (Throwable $e) {
			DB::getConnection()->rollback();
			$request->passErrors[] = 'Saving failed - '.$e->getMessage();
		}

		$this->saveResponse($request);
	}

	/**
	 * @param Request $request
	 *
	 * @return never
	 * @throws JsonException
	 */
	#[Delete('settings/modes/{id}')]
	public function deleteGameMode(Request $request) : never {
		$id = $this->getRequestId($request);

		try {
			$mode = GameModeFactory::getById($id);
			if (!isset($mode)) {
				throw new GameModeNotFoundException();
			}
		} catch (GameModeNotFoundException $e) {
			$this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
		}

		if (!$mode instanceof CustomTeamMode && !$mode instanceof CustomSoloMode) {
			$this->respond(['error' => 'Cannot delete default mode'], 405);
		}

		if (!$mode->delete()) {
			$this->respond(['error' => 'Delete failed'], 500);
		}

		$this->respond(['status' => 'ok']);
	}

	#[Post('settings/modes/new/{system}/{type}')]
	public function createGameMode(Request $request) : never {
		$type = strtoupper($request->params['type'] ?? 'TEAM');
		$system = $request->params['system'] ?? '';

		if (empty($system) || !in_array($system, GameFactory::getSupportedSystems(), true)) {
			$this->respond(['error' => 'Invalid system'], 400);
		}

		DB::insert(AbstractMode::TABLE, [
			'system'    => $system,
			'type'      => $type,
			'name'      => lang('Nový mód', context: 'gameModes'),
			'load_name' => 'game-mode',
		]);
		try {
			$mode = GameModeFactory::getById(DB::getInsertId());
			if (!isset($mode)) {
				throw new GameModeNotFoundException();
			}
		} catch (GameModeNotFoundException|Exception $e) {
			$this->respond(['error' => 'Save failed', 'exception' => $e]);
		}

		$this->respond($mode);
	}
}