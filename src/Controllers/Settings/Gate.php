<?php

namespace App\Controllers\Settings;

use App\Core\App;
use App\Core\Info;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Models\GateScreenModel;
use App\Gate\Models\GateType;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use App\Http\Requests\Settings\Gate\GateSaveInfo;
use App\Http\Requests\Settings\Gate\GateSaveRequest;
use App\Http\Requests\Settings\Gate\ScreenSaveInfo;
use Dibi\Exception;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Enums\ErrorType;
use Lsr\Core\Requests\Request;
use Lsr\Core\Requests\Validation\RequestValidationMapper;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

class Gate extends Controller
{
    protected string $title = 'Nastavení - Výsledková tabule';

    public function __construct(
      private readonly RequestValidationMapper $requestMapper,
    ) {}


    /**
     * @return ResponseInterface
     * @throws TemplateDoesNotExistException
     * @throws ValidationException
     */
    public function gate() : ResponseInterface {
        $this->params['gates'] = GateType::getAll();
        $this->params['screens'] = [];
        foreach (App::getContainer()->findByType(GateScreen::class) as $key) {
            /** @var GateScreen $screen */
            $screen = App::getService($key);
            $this->params['screens'][$screen::getGroup()] ??= [];
            $this->params['screens'][$screen::getGroup()][$key] = $screen;
        }

        $this->params['addCss'][] = 'pages/gateSettings.css';

        return $this->view('pages/settings/gate');
    }

    public function screenSettings(string $screen, Request $request) : ResponseInterface {
        /** @var GateScreen $screenObject */
        $screenObject = App::getService($screen);
        $this->params['screen'] = GateScreenModel::createFromScreen($screenObject);
        $this->params['gateKey'] = $request->getGet('gateKey');
        $this->params['screenKey'] = $request->getGet('screenKey');
        $this->params['formName'] = $request->getGet('formName', 'gate');
        $this->params['formName2'] = $request->getGet('formName2', 'screen');

        if ($screenObject instanceof WithSettings) {
            return $this->view('components/settings/gateScreenSettings');
        }
        return $this->respond(
          new ErrorResponse(
            'Invalid screen',
            ErrorType::VALIDATION,
            'This screen doesn\'t have any settings.'
          ),
          400
        );
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     */
    public function saveGate(Request $request) : ResponseInterface {
        $data = $this->requestMapper->setRequest($request)->mapBodyToObject(GateSaveRequest::class);

        try {
            if (isset($data->timerOffset)) {
                Info::set('timer-offset', $data->timerOffset);
            }
            if (isset($data->timerShow)) {
                Info::set('timer_show', $data->timerShow);
            }
            Info::set('timer_on_inactive_screen', $data->timerOnInactiveScreen);
            $files = $request->getUploadedFiles();
            if (
              isset($files['background']) &&
              $files['background'] instanceof UploadedFile &&
              $files['background']->getError() === UPLOAD_ERR_OK
            ) {
                $file = $files['background'];
                // Remove old uploaded files
                $files = glob(UPLOAD_DIR.'gate.*');
                assert($files !== false);
                foreach ($files as $old) {
                    unlink($old);
                }
                // Save new file
                $extension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                $file->moveTo(UPLOAD_DIR.'gate.'.$extension);
            }
        } catch (Exception) {
            $request->passErrors[] = lang('Failed to save settings.', context: 'errors');
        }

        $newGateIds = [];
        $newScreenIds = [];

        // Update existing gates
        foreach ($data->gate as $id => $gateData) {
            try {
                $gateType = GateType::get((int) $id);
            } catch (ModelNotFoundException $e) {
                bdump('Gate type #'.$id.' not found.');
                bdump($e);
                continue;
            }
            $this->processGateType($gateType, $gateData, $request, $id, $newGateIds, $newScreenIds);
        }

        // Create new gates
        foreach ($data->newGate as $key => $gateData) {
            $gateType = new GateType();
            $this->processGateType($gateType, $gateData, $request, $key, $newGateIds, $newScreenIds);
        }

        // Remove deleted gates
        foreach ($data->deleteGate as $id) {
            try {
                $gateType = GateType::get((int) $id);
            } catch (ModelNotFoundException $e) {
                bdump('Gate type #'.$id.' not found.');
                bdump($e);
                continue;
            }
            if (!$gateType->delete()) {
                $request->passErrors[] = 'Failed to delete gate type.';
            }
        }

        if ($request->isAjax()) {
            bdump($request->params);
            return $this->respond(
              [
                'success'    => empty($request->passErrors),
                'errors'     => $request->passErrors,
                'newGateIds' => $newGateIds,
                'newScreenIds' => $newScreenIds,
              ]
            );
        }
        return $this->app->redirect('settings-gate', $request);
    }

    /**
     * @param  GateType  $gateType
     * @param  GateSaveInfo  $gateData
     * @param  Request  $request
     * @param  string|int  $gateKey
     * @param  array<string|int,int>  $newGateIds
     * @param  array<string|int,int[]>  $newScreenIds
     * @return void
     * @throws ValidationException
     */
    private function processGateType(
      GateType     $gateType,
      GateSaveInfo $gateData,
      Request      $request,
      string | int $gateKey,
      array        &$newGateIds,
      array        &$newScreenIds
    ) : void {
        $new = !isset($gateType->id);
        bdump($new);
        if (!empty($gateData->name)) {
            $gateType->setName($gateData->name);
        }
        if (!empty($gateData->slug)) {
            $gateType->setSlug($gateData->slug);
        }

        // Process screens
        foreach ($gateData->screen as $screenId => $screenData) {
            try {
                $screenModel = GateScreenModel::get((int) $screenId);
            } catch (ModelNotFoundException $e) {
                bdump('Gate screen #'.$screenId.' not found.');
                bdump($e);
                continue;
            }
            $this->processScreen($screenModel, $screenData);
            $screenModel->save();
        }

        $newScreenIds[$gateKey] ??= [];
        $newScreens = [];
        foreach ($gateData->newScreen as $key => $screenData) {
            $screenModel = new GateScreenModel();
            $this->processScreen($screenModel, $screenData);
            $gateType->addScreenModel($screenModel);
            $newScreens[$key] = $screenModel;
        }

        if (!empty($gateData->deleteScreen)) {
            $screens = $gateType->screens;
            foreach ($gateData->deleteScreen as $id) {
                try {
                    $screenModel = GateScreenModel::get($id);
                } catch (ModelNotFoundException $e) {
                    bdump('Gate screen #'.$id.' not found.');
                    bdump($e);
                    continue;
                }

                foreach ($screens as $key => $screen) {
                    if ($screen->id === $screenModel->id) {
                        unset($screens[$key]);
                    }
                }
                $screenModel->delete();
            }
            $gateType->screens = $screens;
        }

        if (!empty($gateType->name) && count($gateType->screens) > 0) {
            if (!$gateType->save()) {
                $request->passErrors[] = sprintf(
                  lang('Nepodařilo se uložit výsledkovou tabuli %s.', context: 'errors'),
                  $gateType->name
                );
            }
            else {
                if ($new || $newScreens) {
                    assert($gateType->id !== null);
                    $newGateIds[$gateKey] = $gateType->id;
                    foreach ($newScreens as $key => $screen) {
                        if (isset($screen->id)) {
                            $newScreenIds[$gateKey][$key] = $screen->id;
                        }
                    }
                }
            }
        }
    }

    private function processScreen(GateScreenModel $screenModel, ScreenSaveInfo $screenData) : void {
        if (
          $screenData->type !== null
          && (!isset($screenModel->screenSerialized) || $screenData->type !== $screenModel->screenSerialized)
        ) {
            $screen = App::getService($screenData->type);
            if (!($screen instanceof GateScreen)) {
                throw new ValidationException('Invalid screen type: '.$screenData->type);
            }
            $screenModel->setScreen($screen);
        }
        if ($screenData->trigger !== null) {
            $screenModel->setTrigger($screenData->trigger);
        }
        if ($screenData->order !== null) {
            $screenModel->setOrder((int) $screenData->order);
        }
        if ($screenData->triggerValue !== null && $screenModel->trigger === ScreenTriggerType::CUSTOM) {
            $screenModel->setTriggerValue($screenData->triggerValue);
        }
        if ($screenData->settings !== null && ($screen = $screenModel->getScreen()) instanceof WithSettings) {
            $screenModel->setSettings($screen::buildSettingsFromForm($screenData->settings));
        }
    }
}
