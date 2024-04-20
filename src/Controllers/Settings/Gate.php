<?php

namespace App\Controllers\Settings;

use App\Api\Response\ErrorDto;
use App\Api\Response\ErrorType;
use App\Core\App;
use App\Core\Info;
use App\Gate\Logic\ScreenTriggerType;
use App\Gate\Models\GateScreenModel;
use App\Gate\Models\GateType;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ModelNotFoundException;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

class Gate extends Controller
{

    protected string $title = 'Settings - Gate';


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
          new ErrorDto(
            'Invalid screen', ErrorType::VALIDATION, 'This screen doesn\'t have any settings.'
          ),
          400
        );
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function saveGate(Request $request) : ResponseInterface {
        try {
            $offset = $request->getPost('timer_offset');
            if (isset($offset)) {
                Info::set('timer-offset', (int) $offset);
            }
            $show = $request->getPost('timer_show');
            if (isset($show)) {
                Info::set('timer_show', (int) $show);
            }
            Info::set('timer_on_inactive_screen', !empty($request->getPost('timer_on_inactive_screen')));
            $files = $request->getUploadedFiles();
            if (
              isset($files['background']) &&
              $files['background'] instanceof UploadedFile &&
              $files['background']->getError() === UPLOAD_ERR_OK
            ) {
                /** @var UploadedFile $file */
                $file = $files['background'];
                // Remove old uploaded files
                foreach (glob(UPLOAD_DIR.'gate.*') as $old) {
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
        foreach ($request->getPost('gate', []) as $id => $gateData) {
            try {
                $gateType = GateType::get((int) $id);
            } catch (ModelNotFoundException | ValidationException $e) {
                bdump('Gate type #'.$id.' not found.');
                bdump($e);
                continue;
            }
            $this->processGateType($gateType, $gateData, $request, $id, $newGateIds, $newScreenIds);
        }

        // Create new gates
        foreach ($request->getPost('new-gate', []) as $key => $gateData) {
            $gateType = new GateType();
            $this->processGateType($gateType, $gateData, $request, $key, $newGateIds, $newScreenIds);
        }

        // Remove deleted gates
        foreach ($request->getPost('delete-gate', []) as $id) {
            try {
                $gateType = GateType::get((int) $id);
            } catch (ModelNotFoundException | ValidationException $e) {
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
                'success' => empty($request->passErrors),
                'errors'  => $request->passErrors,
                'newGateIds' => $newGateIds,
                'newScreenIds' => $newScreenIds,
              ]
            );
        }
        return App::redirect('settings-gate', $request);
    }

    /**
     * @param  GateType  $gateType
     * @param  array{name?:string,slug?:string,screen?:array{type?:string,trigger?:string,trigger_value?:string,settings?:array<string,mixed>}[],new-screen?:array{type?:string,trigger?:string,trigger_value?:string,settings?:array<string,mixed>}[],delete-screens?:numeric-string[]}  $gateData
     * @param  Request  $request
     * @param  string|int  $gateKey
     * @param  array  $newGateIds
     * @param  array  $newScreenIds
     * @return void
     * @throws ValidationException
     */
    private function processGateType(
      GateType     $gateType,
      array        $gateData,
      Request      $request,
      string | int $gateKey,
      array        &$newGateIds,
      array        &$newScreenIds
    ) : void {
        $new = !isset($gateType->id);
        $newScreens = false;
        bdump($new);
        if (!empty($gateData['name'])) {
            $gateType->setName($gateData['name']);
        }
        if (!empty($gateData['slug'])) {
            $gateType->setSlug($gateData['slug']);
        }

        // Process screens
        foreach ($gateData['screen'] ?? [] as $screenId => $screenData) {
            try {
                $screenModel = GateScreenModel::get((int) $screenId);
            } catch (ModelNotFoundException | ValidationException $e) {
                bdump('Gate screen #'.$screenId.' not found.');
                bdump($e);
                continue;
            }
            $this->processScreen($screenModel, $screenData);
            $screenModel->save();
        }

        $newScreenIds[$gateKey] ??= [];
        $newScreens = [];
        foreach ($gateData['new-screen'] ?? [] as $key => $screenData) {
            $screenModel = new GateScreenModel();
            $this->processScreen($screenModel, $screenData);
            $gateType->addScreenModel($screenModel);
            $newScreens[$key] = $screenModel;
        }

        if (isset($gateData['delete-screens'])) {
            $screens = $gateType->getScreens();
            foreach ($gateData['delete-screens'] as $id) {
                try {
                    $screenModel = GateScreenModel::get((int) $id);
                } catch (ModelNotFoundException | ValidationException $e) {
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

        if (!empty($gateType->name) && count($gateType->getScreens()) > 0) {
            if (!$gateType->save()) {
                $request->passErrors[] = sprintf(
                  lang('Nepodařilo se uložit výsledkovou tabuli %s.', context: 'errors'),
                  $gateType->name
                );
            }
            elseif ($new || $newScreens) {
                $newGateIds[$gateKey] = $gateType->id;
                foreach ($newScreens as $key => $screen) {
                    if (isset($screen->id)) {
                        $newScreenIds[$gateKey][$key] = $screen->id;
                    }
                }
            }
        }
    }

    /**
     * @param  GateScreenModel  $screenModel
     * @param  array{type?:string,order?:numeric,trigger?:string,trigger_value?:string,settings?:array<string,mixed>}  $screenData
     * @return void
     */
    private function processScreen(GateScreenModel $screenModel, array $screenData) : void {
        if (isset($screenData['type']) && (!isset($screenModel->screenSerialized) || $screenData['type'] !== $screenModel->screenSerialized)) {
            // @phpstan-ignore-next-line
            $screenModel->setScreen(App::getService($screenData['type']));
        }
        if (isset($screenData['trigger'])) {
            $screenModel->setTrigger(ScreenTriggerType::from($screenData['trigger']));
        }
        if (isset($screenData['order'])) {
            $screenModel->setOrder((int) $screenData['order']);
        }
        if (isset($screenData['trigger_value']) && $screenModel->trigger === ScreenTriggerType::CUSTOM) {
            $screenModel->setTriggerValue($screenData['trigger_value']);
        }
        if (isset($screenData['settings']) && ($screen = $screenModel->getScreen()) instanceof WithSettings) {
            $screenModel->setSettings($screen::buildSettingsFromForm($screenData['settings']));
        }
    }

}