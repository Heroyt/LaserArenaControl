<?php

namespace App\Controllers\Settings;

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
use Lsr\Core\Caching\Cache;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\DB;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 *
 */
class Modes extends Controller
{
    public function __construct(
        private readonly Cache $cache,
    ) {
        parent::__construct();
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws GameModeNotFoundException
     * @throws JsonException
     * @throws TemplateDoesNotExistException
     */
    public function modes(Request $request): ResponseInterface {
        $this->params['system'] = $request->params['system'] ?? first(GameFactory::getSupportedSystems());
        $this->params['modes'] = GameModeFactory::getAll(['system' => $this->params['system'], 'all' => true]);
        return $this->view('pages/settings/modes');
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function modeVariations(Request $request): ResponseInterface {
        $id = $this->getRequestId($request);
        if ($id instanceof ErrorResponse) {
            $this->respond($id, 400);
        }

        try {
            $mode = GameModeFactory::getById($id);
            if (!isset($mode)) {
                throw new GameModeNotFoundException();
            }
        } catch (GameModeNotFoundException $e) {
            return $this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
        }

        $variations = [];
        foreach ($mode->getVariations() as $variationId => $values) {
            $variations[$variationId] = [
                'variation' => $values[0]->variation,
                'values'    => $values,
            ];
        }

        return $this->respond([
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
    protected function getRequestId(Request $request): int|ErrorResponse {
        $id = (int) ($request->params['id'] ?? 0);
        if ($id <= 0) {
            return new ErrorResponse('Invalid parameter id');
        }
        return $id;
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function modeSettings(Request $request): ResponseInterface {
        $id = $this->getRequestId($request);
        if ($id instanceof ErrorResponse) {
            $this->respond($id, 400);
        }

        try {
            $mode = GameModeFactory::getById($id);
            if (!isset($mode)) {
                throw new GameModeNotFoundException();
            }
        } catch (GameModeNotFoundException $e) {
            return $this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
        }

        return $this->respond($mode->settings);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function modeNames(Request $request): ResponseInterface {
        $id = $this->getRequestId($request);
        if ($id instanceof ErrorResponse) {
            $this->respond($id, 400);
        }

        try {
            $mode = GameModeFactory::getById($id);
            if (!isset($mode)) {
                throw new GameModeNotFoundException();
            }
        } catch (GameModeNotFoundException $e) {
            return $this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
        }

        $names = DB::select('[game_modes-names]', 'sysName')->where('id_mode = %i', $id)->fetchPairs(cache: false);
        return $this->respond($names);
    }

    public function saveModeNames(Request $request): ResponseInterface {
        $id = $this->getRequestId($request);
        if ($id instanceof ErrorResponse) {
            $this->respond($id, 400);
        }

        /** @var string[] $names */
        $names = $request->getPost('modeNames', []);

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
        } catch (DriverException | \Dibi\Exception $e) {
            DB::getConnection()->rollback();
            return $this->respond(
                ['error' => 'Failed to save the data to database', 'exception' => $e->getMessage()],
                500
            );
        }
        // Clear cache
        $this->cache->clean([$this->cache::Tags => ['templates.mode', AbstractMode::TABLE]]);
        return $this->respond(['status' => 'ok']);
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     * @throws ValidationException
     */
    public function getAllVariations(): ResponseInterface {
        return $this->respond(GameModeVariation::getAll());
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws ValidationException
     */
    public function createVariation(Request $request): ResponseInterface {
        $name = $request->getPost('name', '');
        if (empty($name)) {
            return $this->respond(['error' => lang('Název nesmí být prázdný', context: 'errors')], 400);
        }

        $name = trim($name);

        // Check duplicate names
        $test = GameModeVariation::query()->where('[name] = %s', $name)->first();
        if (isset($test)) {
            return $this->respond($test);
        }

        $variation = new GameModeVariation();
        $variation->name = $name;
        if ($variation->save()) {
            return $this->respond($variation);
        }
        // Clear cache
        $this->cache->clean([$this->cache::Tags => ['mode.variations', 'templates.mode', GameModeVariation::TABLE]]);
        return $this->respond(['error' => lang('Nepodařilo se vytvořit variaci', context: 'errors')], 500);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function save(Request $request): ResponseInterface {
        $modes = [];
        foreach ($request->getPost('mode', []) as $id => $values) {
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
            } else if (isset($values['name'])) {
                $mode->alias = $values['name'] === $mode->name ? '' : $values['name'];
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
            $mode->active = !empty($values['active']);
            $mode->public = !empty($values['public']);
            if (!empty($values['teams'])) {
                $mode->teams = json_encode($values['teams'], JSON_THROW_ON_ERROR);
            }
            try {
                if (!$mode->save()) {
                    $request->passErrors[] = lang('Nepodařilo se uložit herní mód', context: 'errors') . ': ' . $mode->name;
                    continue;
                }
                $modes[$mode->id] = $mode;
            } catch (ValidationException $e) {
                $request->passErrors[] = lang('Validace selhala', context: 'errors') . ' - ' . $e->getMessage();
            }
        }
        // Clear cache
        $this->cache->clean([$this->cache::Tags => ['mode.variations', 'templates.mode', AbstractMode::TABLE]]);
        return $this->saveResponse($request, ['modes' => $modes]);
    }

    /**
     * @param  Request  $request
     * @param  array<string, mixed>  $additional
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    private function saveResponse(Request $request, array $additional = []): ResponseInterface {
        if ($request->isAjax()) {
            if (!empty($request->passErrors)) {
                return $this->respond(
                    array_merge($additional, ['errors' => $request->passErrors, 'notices' => $request->passNotices]),
                    500
                );
            }
            return $this->respond(array_merge($additional, ['status' => 'ok', 'notices' => $request->passNotices]));
        }
        return $this->app->redirect(['settings', 'modes'], $request);
    }

    public function saveModeVariations(Request $request): ResponseInterface {
        $id = (int) ($request->params['id'] ?? 0);
        if ($id <= 0) {
            $request->passErrors[] = 'Invalid parameter id';
            return $this->saveResponse($request);
        }

        try {
            $mode = GameModeFactory::getById($id);
            if (!isset($mode)) {
                throw new GameModeNotFoundException();
            }
        } catch (GameModeNotFoundException $e) {
            $request->passErrors[] = 'Game mode not found';
            return $this->saveResponse($request);
        }

        /** @var array{name:string,values:array{value?:string,suffix?:string,order?:int}[]}[] $variations */
        $variations = $request->getPost('variation', []);

        try {
            DB::getConnection()->begin();
            DB::delete(GameModeVariation::TABLE_VALUES, ['[id_mode] = %i', $id]);
            foreach ($variations as $variationId => $info) {
                $variation = GameModeVariation::get($variationId);
                $variation->name = $info['name'];
                $variation->public = !empty($info['public']);
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
                DB::select(GameModeVariation::TABLE_VALUES, 'id_variation')->fluent
            ]);
            DB::getConnection()->commit();
            // Clear cache
            $this->cache->clean([$this->cache::Tags => ['mode.' . $id . '.variations', 'templates.mode', GameModeVariation::TABLE, GameModeVariation::TABLE_VALUES]]);
        } catch (Throwable $e) {
            DB::getConnection()->rollback();
            $request->passErrors[] = 'Saving failed - ' . $e->getMessage();
        }

        return $this->saveResponse($request);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function deleteGameMode(Request $request): ResponseInterface {
        $id = $this->getRequestId($request);
        if ($id instanceof ErrorResponse) {
            $this->respond($id, 400);
        }

        try {
            $mode = GameModeFactory::getById($id);
            if (!isset($mode)) {
                throw new GameModeNotFoundException();
            }
        } catch (GameModeNotFoundException $e) {
            return $this->respond(['error' => 'Game mode not found', 'exception' => $e->getMessage()], 404);
        }

        if (!$mode instanceof CustomTeamMode && !$mode instanceof CustomSoloMode) {
            return $this->respond(['error' => 'Cannot delete default mode'], 405);
        }

        if (!$mode->delete()) {
            return $this->respond(['error' => 'Delete failed'], 500);
        }
        // Clear cache
        $this->cache->clean([$this->cache::Tags => ['templates.mode']]);

        return $this->respond(['status' => 'ok']);
    }

    public function createGameMode(Request $request): ResponseInterface {
        $type = strtoupper($request->params['type'] ?? 'TEAM');
        $system = $request->params['system'] ?? '';

        if (empty($system) || !in_array($system, GameFactory::getSupportedSystems(), true)) {
            return $this->respond(['error' => 'Invalid system'], 400);
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
        } catch (GameModeNotFoundException | Exception $e) {
            return $this->respond(['error' => 'Save failed', 'exception' => $e]);
        }

        // Clear cache
        $this->cache->clean([$this->cache::Tags => ['templates.mode', AbstractMode::TABLE]]);

        return $this->respond($mode);
    }
}
