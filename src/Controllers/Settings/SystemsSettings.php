<?php
declare(strict_types=1);

namespace App\Controllers\Settings;

use App\DataObjects\Request\Settings\CreateSystemData;
use App\DataObjects\Request\Settings\SaveSystemsData;
use App\GameModels\Vest;
use App\Models\System;
use App\Templates\Settings\SystemsSettingsParameters;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\Exceptions\ValidationException;
use Lsr\Serializer\Mapper;
use Psr\Http\Message\ResponseInterface;

class SystemsSettings extends Controller
{

    public function __construct(
      private readonly Mapper $mapper
    ) {}

    public function show() : ResponseInterface {
        $this->params = new SystemsSettingsParameters($this->params);

        $this->params->systems = System::getAll();
        foreach ($this->params->systems as $system) {
            $this->params->vests[$system->id] = [];
            $this->params->vestsGrid[$system->id] = [];
            $this->params->columnCounts[$system->id] = $system->columnCount;
            $this->params->rowCounts[$system->id] = $system->rowCount;
        }

        foreach (Vest::getAll() as $vest) {
            $this->params->vests[$vest->system->id][] = $vest;
            $row = max($vest->gridRow, 1);
            $col = max($vest->gridCol, 1);
            $this->params->vestsGrid[$vest->system->id][$row] ??= [];

            // If duplicate, find first available column
            while (isset($this->params->vestsGrid[$vest->system->id][$row][$col])) {
                $col++;
            }

            $this->params->columnCounts[$vest->system->id] = max($this->params->columnCounts[$vest->system->id], $col);
            $this->params->rowCounts[$vest->system->id] = max($this->params->rowCounts[$vest->system->id], $row);

            $this->params->vestsGrid[$vest->system->id][$row][$col] = $vest;
        }

        return $this->view('pages/settings/systems');
    }

    public function create(Request $request) : ResponseInterface {
        try {
            $data = new RequestValidationMapper($this->mapper)->setRequest($request)->mapBodyToObject(
              CreateSystemData::class
            );
        } catch (ValidationException $e) {
            return $this->respond(
              new ErrorResponse(
                           lang('Chyba při validaci dat'),
                           ErrorType::VALIDATION,
                           $e->getMessage(),
                exception: $e,
              ),
              400
            );
        }

        $system = new System();
        $system->name = $data->name;
        $system->type = $data->type;

        if (!$system->save()) {
            return $this->respond(
              new ErrorResponse(lang('Chyba při ukládání systému'), ErrorType::DATABASE),
              500
            );
        }

        // Create vests for system
        for ($i = 1; $i <= $data->vests; $i++) {
            $vest = new Vest();
            $vest->vestNum = (string) $i;
            $vest->system = $system;
            $vest->save();
        }

        $system::clearModelCache();
        Vest::clearModelCache();

        if ($request->isAjax()) {
            return $this->respond(new SuccessResponse(values: ['id' => $system->id]));
        }
        return $this->redirect('settings-systems');
    }

    public function save(Request $request) : ResponseInterface {
        try {
            $data = new RequestValidationMapper($this->mapper)->setRequest($request)->mapBodyToObject(
              SaveSystemsData::class
            );
        } catch (ValidationException $e) {
            return $this->respond(
              new ErrorResponse(
                           lang('Chyba při validaci dat'),
                           ErrorType::VALIDATION,
                           $e->getMessage(),
                exception: $e,
              ),
              400
            );
        }

        foreach ($data->systems as $id => $systemData) {
            try {
                $system = System::get((int) $id);
            } catch (ModelNotFoundException $e) {
                return $this->respond(
                  new ErrorResponse(
                               lang('Systém neexistuje'),
                               ErrorType::NOT_FOUND,
                               $e->getMessage(),
                    exception: $e,
                  ),
                  404
                );
            }

            $system->name = $systemData->name;
            $system->type = $systemData->type;
            $system->systemIp = $systemData->ip;
            $system->resultsDir = $systemData->results_dir;
            $system->gameLoadDir = $systemData->load_dir;
            $system->musicDir = $systemData->music_dir;
            $system->default = $systemData->default;
            $system->active = $systemData->active;
            $system->columnCount = $systemData->columns;
            $system->rowCount = $systemData->rows;

            if (!$system->save()) {
                return $this->respond(
                  new ErrorResponse(lang('Chyba při ukládání systému'), ErrorType::DATABASE),
                  500
                );
            }
            $system->clearCache();
        }

        foreach ($data->vests as $id => $vestData) {
            try {
                $vest = Vest::get($id);
            } catch (ModelNotFoundException $e) {
                return $this->respond(
                  new ErrorResponse(
                               lang('Vesta neexistuje'),
                               ErrorType::NOT_FOUND,
                               $e->getMessage(),
                    exception: $e,
                  ),
                  404
                );
            }

            $vest->vestNum = $vestData->vest_num ?? $vest->vestNum;
            $vest->status = $vestData->status ?? $vest->status;
            $vest->info = $vestData->info ?? $vest->info;
            $vest->type = $vestData->type ?? $vest->type;
            $vest->gridCol = $vestData->col ?? $vest->gridCol;
            $vest->gridRow = $vestData->row ?? $vest->gridRow;

            if (!$vest->save()) {
                return $this->respond(
                  new ErrorResponse(lang('Chyba při ukládání vesty'), ErrorType::DATABASE),
                  500
                );
            }
            $vest->clearCache();
        }

        if ($request->isAjax()) {
            return $this->respond(new SuccessResponse());
        }
        return $this->redirect('settings-systems');
    }

    public function addVests(System $system, Request $request) : ResponseInterface {
        $count = (int) $request->getPost('count');

        $systemCount = Vest::getVestCount($system);
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $vest = new Vest();
            $vest->vestNum = (string) ($systemCount + $i);
            $vest->system = $system;
            $vest->save();
            $ids[] = $vest->id;
        }

        Vest::clearModelCache();

        if ($request->isAjax()) {
            return $this->respond(new SuccessResponse(values: ['ids' => $ids]));
        }
        return $this->redirect('settings-systems');
    }

    public function deleteVest(Vest $vest, Request $request) : ResponseInterface {
        $vest->delete();
        Vest::clearModelCache();

        if ($request->isAjax()) {
            return $this->respond(new SuccessResponse());
        }
        return $this->redirect('settings-systems');
    }

}