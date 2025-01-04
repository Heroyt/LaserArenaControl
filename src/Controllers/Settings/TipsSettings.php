<?php

namespace App\Controllers\Settings;

use App\GameModels\Tip;
use Dibi\DriverException;
use JsonException;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Dto\ErrorResponse;
use Lsr\Core\Requests\Dto\SuccessResponse;
use Lsr\Core\Requests\Request;
use Lsr\Core\Translations;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class TipsSettings extends Controller
{
    public string $title = 'Nastavení - Tipy';

    public function __construct(
      private readonly Translations $translations,
    ) {
        parent::__construct();
    }

    /**
     * @return ResponseInterface
     * @throws JsonException
     * @throws ModelNotFoundException
     * @throws TemplateDoesNotExistException
     * @throws ValidationException
     */
    public function show() : ResponseInterface {
        $this->params['tips'] = Tip::getAll();
        $this->params['languages'] = $this->translations->supportedLanguages;
        return $this->view('pages/settings/tips');
    }

    /**
     * @throws DriverException
     * @throws JsonException
     */
    public function save(Request $request) : ResponseInterface {
        $ids = [
          'old' => [],
          'new' => [],
        ];
        $tips = $request->getPost('tip', []);
        if (is_array($tips)) {
            /**
             * @var numeric-string $id
             * @var array{text?:string,translations?:string[]} $data
             */
            foreach ($tips as $id => $data) {
                $saveId = false;
                try {
                    $tip = Tip::get((int) $id);
                } catch (ModelNotFoundException) {
                    // Create new tip
                    $tip = new Tip();
                    $saveId = true;
                }

                $tip->text = str_replace("\n", '', trim($data['text'] ?? $tip->text));
                foreach ($data['translations'] ?? [] as $lang => $text) {
                    $tip->setTranslation($lang, str_replace("\n", '', trim($text)));
                }

                if ($tip->save() && $saveId) {
                    $tips['old'][$id] = $tip->id;
                }
            }
        }
        else {
            $request->passErrors[] = lang('Chybný požadavek');
        }

        $newTips = $request->getPost('new_tip', []);
        if (is_array($newTips)) {
            /**
             * @var numeric-string $id
             * @var array{text?:string,translations?:string[]} $data
             */
            foreach ($newTips as $id => $data) {
                // Create new tip
                $tip = new Tip();

                $tip->text = str_replace("\n", '', trim($data['text'] ?? $tip->text));
                foreach ($data['translations'] ?? [] as $lang => $text) {
                    $tip->setTranslation($lang, str_replace("\n", '', trim($text)));
                }

                if ($tip->save()) {
                    $ids['new'][$id] = $tip->id;
                }
            }
        }
        else {
            $request->passErrors[] = lang('Chybný požadavek');
        }

        if ($request->isAjax()) {
            return $this->respond(
              [
                'success' => empty($request->passErrors),
                'errors'  => $request->passErrors,
                'ids' => $ids,
              ],
              empty($request->passErrors) ? 200 : 500
            );
        }
        return $this->app->redirect('settings-tips', $request);
    }

    public function remove(Tip $tip, Request $request) : ResponseInterface {
        if (!$tip->delete()) {
            $err = lang('Nepodařilo se odstranit entitu', context: 'errors');
            if ($request->isAjax()) {
                return $this->respond(new ErrorResponse($err), 500);
            }

            $request->passErrors[] = $err;
            return $this->app->redirect('settings-tips', $request);
        }

        if ($request->isAjax()) {
            return $this->respond(new SuccessResponse());
        }
        return $this->app->redirect('settings-tips', $request);
    }
}
