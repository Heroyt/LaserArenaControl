<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Controllers\Settings;

use App\Core\Info;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Vest;
use App\Models\DataObjects\Theme;
use App\Models\GameGroup;
use App\Models\PriceGroup;
use App\Models\System;
use App\Services\FeatureConfig;
use Dibi\Exception;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Lsr\LaserLiga\Enums\VestStatus;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 *
 */
class Settings extends Controller
{
    protected string $title = 'Nastavení';

    public function __construct(
      private readonly FeatureConfig $featureConfig,
    ) {}

    public function init(RequestInterface $request) : void {
        parent::init($request);
        $this->params['featureConfig'] = $this->featureConfig;
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     * @throws TemplateDoesNotExistException
     */
    public function show() : ResponseInterface {
        $this->params['theme'] = Theme::get();
        $this->params['priceGroups'] = PriceGroup::getAll();
        return $this->view('pages/settings/index');
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     * @throws TemplateDoesNotExistException
     * @throws ValidationException
     */
    public function vests() : ResponseInterface {
        $vests = Vest::getAll();
        $this->params['systems'] = System::getAll();
        $this->params['vests'] = [];
        $this->params['vestsGrid'] = [];

        foreach (GameFactory::getSupportedSystems() as $system) {
            $this->params['vests'][$system] = [];
            $this->params['vestsGrid'][$system] = [];
        }

        $this->params['columnCounts'] = [];
        $this->params['rowCounts'] = [];

        foreach ($vests as $vest) {
            $this->params['columnCounts'][$vest->system->id] ??= $vest->system->columnCount;
            $this->params['rowCounts'][$vest->system->id] ??= $vest->system->rowCount;

            $this->params['vests'][$vest->system->id][] = $vest;
            $row = max($vest->gridRow, 1);
            $col = max($vest->gridCol, 1);

            $this->params['vestsGrid'][$vest->system->id][$row] ??= [];

            // If duplicate, find first available column
            while (isset($this->params['vestsGrid'][$vest->system->id][$row][$col])) {
                $col++;
            }

            $this->params['columnCounts'][$vest->system->id] = max(
              $this->params['columnCounts'][$vest->system->id],
              $col
            );
            $this->params['rowCounts'][$vest->system->id] = max($this->params['rowCounts'][$vest->system->id], $row);

            $this->params['vestsGrid'][$vest->system->id][$row][$col] = $vest;
        }
        return $this->view('pages/settings/vests');
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function saveVests(Request $request) : ResponseInterface {
        try {
            $systems = System::getAll();
            /** @var array<numeric,numeric> $post */
            $post = $request->getPost('columns', []);
            foreach ($post as $systemId => $count) {
                if (!isset($systems[(int) $systemId])) {
                    throw new ModelNotFoundException('System not found');
                }
                $systems[(int) $systemId]->columnCount = (int) $count;
            }
            /** @var array<numeric, numeric> $post */
            $post = $request->getPost('rows', []);
            foreach ($post as $systemId => $count) {
                if (!isset($systems[(int) $systemId])) {
                    throw new ModelNotFoundException('System not found');
                }
                $systems[(int) $systemId]->rowCount = (int) $count;
            }
            foreach ($systems as $system) {
                $system->save();
            }
            /** @var array<numeric, array{vest_num?:string,status?:string,info?:string,col?:numeric,row?:numeric}> $post */
            $post = $request->getPost('vest', []);
            foreach ($post as $id => $info) {
                $vest = Vest::get((int) $id);
                $vest->vestNum = $info['vest_num'] ?? $vest->vestNum;
                $vest->status = VestStatus::from($info['status'] ?? $vest->status->value);
                $vest->info = $info['info'] ?? $vest->info;
                $vest->gridCol = (int) ($info['col'] ?? $vest->gridCol);
                $vest->gridRow = (int) ($info['row'] ?? $vest->gridRow);
                $vest->save();
                $vest->clearCache();
            }
        } catch (Exception $e) {
            $request->passErrors[] = lang('Failed to save settings.', context: 'errors');
            $request->passErrors[] = $e->getMessage();
        }
        if ($request->isAjax()) {
            return $this->respond(
              [
                'success' => empty($request->passErrors),
                'errors'  => $request->passErrors,
              ],
              empty($request->passErrors) ? 200 : 400
            );
        }
        return $this->app->redirect('settings', $request);
    }

    /**
     * @param  Request  $request
     *
     * @return ResponseInterface
     * @throws JsonException
     */
    public function saveGeneral(Request $request) : ResponseInterface {
        try {
            $apiUrl = $request->getPost('api_url');
            if (isset($apiUrl)) {
                Info::set('liga_api_url', $apiUrl);
            }
            $apiKey = $request->getPost('api_key');
            if (isset($apiKey)) {
                Info::set('liga_api_key', $apiKey);
            }
            $arenaId = $request->getPost('arena_id');
            if (isset($arenaId) && is_numeric($arenaId)) {
                Info::set('liga_arena_id', (int) $arenaId);
            }
            $lmx = $request->getPost('lmx_ip');
            if (isset($lmx)) {
                Info::set('lmx_ip', $lmx);
            }
            /** @var string|null $gates */
            $gates = $request->getPost('gates_ips');
            if (isset($gates)) {
                Info::set('gates_ips', array_map('trim', explode(',', $gates)));
            }

            $this->handleLogoUpload($request);

            $this->handlePriceGroups($request);

            // Update theme
            $theme = Theme::get();
            /** @var string|null $primaryColor */
            $primaryColor = $request->getPost('primary_color');
            if (!empty($primaryColor)) {
                $theme->primaryColor = $primaryColor;
            }
            /** @var string|null $secondaryColor */
            $secondaryColor = $request->getPost('secondary_color');
            if (!empty($secondaryColor)) {
                $theme->secondaryColor = $secondaryColor;
            }
            $theme->save();

            // Generate theme css
            file_put_contents(ROOT.'dist/theme.css', $theme->getCss());

        } catch (Exception) {
            $request->passErrors[] = lang('Failed to save settings.', context: 'errors');
        }
        if ($request->isAjax()) {
            return $this->respond(
              [
                'success' => empty($request->passErrors),
                'errors'  => $request->passErrors,
              ]
            );
        }
        return $this->app->redirect('settings', $request);
    }

    private function handleLogoUpload(Request $request) : void {
        $files = $request->getUploadedFiles();
        if (!isset($files['logo'])) {
            return;
        }
        /** @var UploadedFile $file */
        $file = $files['logo'];
        $name = basename($file->getClientFilename());
        // Handle form errors
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $request->passErrors[] = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE   => lang('Nahraný soubor je příliš velký', context: 'errors').' - '.$name,
                UPLOAD_ERR_FORM_SIZE  => lang('Form size is to large', context: 'errors').' - '.$name,
                UPLOAD_ERR_PARTIAL    => lang(
                             'The uploaded file was only partially uploaded.',
                    context: 'errors'
                  ).' - '.$name,
                UPLOAD_ERR_CANT_WRITE => lang('Failed to write file to disk.', context: 'errors').' - '.$name,
                default               => lang('Error while uploading a file.', context: 'errors').' - '.$name,
            };
            return;
        }

        // Remove old uploaded files
        $files = glob(UPLOAD_DIR.'logo.*');
        if ($files !== false) {
            foreach ($files as $old) {
                unlink($old);
            }
        }

        // Validate type
        $validTypes = ['svg', 'jpg', 'png', 'jpeg', 'gif'];
        $fileType = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($fileType, $validTypes)) {
            $request->passErrors[] = lang(
                       'Nahraný soubor musí být v jednom z formátů: %s.',
              context: 'errors',
              format : [implode(', ', $validTypes)]
            );
            return;
        }

        try {
            $file->moveTo(UPLOAD_DIR.'logo.'.$fileType);
        } catch (RuntimeException $e) {
            $request->passErrors[] = lang('File upload failed.', context: 'errors').$e->getMessage();
        }
    }

    private function handlePriceGroups(Request $request) : void {
        /** @var array<numeric, array{name?:string,price?:numeric}>|string $priceGroups */
        $priceGroups = $request->getPost('pricegroups', []);
        if (!is_array($priceGroups) || empty($priceGroups)) {
            return;
        }

        foreach ($priceGroups as $id => $priceGroupData) {
            try {
                $priceGroup = PriceGroup::get((int) $id);
            } catch (ModelNotFoundException $e) {
                $request->passErrors[] = $e->getMessage();
                continue;
            }

            $priceGroup->name = $priceGroupData['name'] ?? $priceGroup->name;
            $priceGroup->setPrice((float) ($priceGroupData['price'] ?? $priceGroup->getPrice()));

            $priceGroup->save();
        }
    }

    public function group() : ResponseInterface {
        $this->params['groupsActive'] = GameGroup::getActiveByDate();
        $this->params['groupsInactive'] = GameGroup::query()->where('active = 0')->orderBy('id_group')->desc()->get();

        return $this->view('pages/settings/groups');
    }
}
