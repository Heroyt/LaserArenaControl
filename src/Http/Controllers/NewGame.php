<?php

/** @noinspection PhpDynamicFieldDeclarationInspection */

namespace App\Http\Controllers;

use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\Gate\Models\GateScreenModel;
use App\Http\Templates\NewGame\NewGameParams;
use App\Models\MusicMode;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Lsr\ObjectValidation\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @property NewGameParams $params
 */
class NewGame extends Controller
{
    use NewGameTrait;

    protected string $title = 'Nová hra';

    public function init(RequestInterface $request) : void {
        parent::init($request);
        $this->baseInit($request);
    }

    /**
     * @param  Request  $request
     * @return ResponseInterface
     * @throws GameModeNotFoundException
     * @throws TemplateDoesNotExistException
     * @throws Throwable
     * @throws ValidationException
     */
    public function show(Request $request) : ResponseInterface {
        $this->initNewGameParams($request);
        $this->params->gameModes = $this->params->system ?
          GameModeFactory::getAll(['system' => $this->params->system])
          : [];
        $this->params->musicModes = MusicMode::getAll();
        $this->initMusicGroups();
        $this->params->addCss = ['pages/newGame.css'];

        /** @var string|null $game */
        $game = $request->getGet('game');

        $this->params->loadGame = !empty($game) ? GameFactory::getByCode($game) : null;

        $gateActionScreens = GateScreenModel::query()->where('trigger_value IS NOT NULL')->get();
        $this->params->gateActions = [];
        foreach ($gateActionScreens as $gateActionScreen) {
            if (!empty($gateActionScreen->triggerValue)) {
                $this->params->gateActions[$gateActionScreen->triggerValue] = $gateActionScreen->triggerValue;
            }
        }

        foreach ($this->decorators as $decorator) {
            if ($decorator->decorates('show') && method_exists($decorator, 'decorateShow')) {
                $decorator->decorateShow();
            }
        }

        return $this->view('pages/new-game/index')
                    ->withAddedHeader('Expires', date('D, d M Y H:i:s T', strtotime('+ 1 minutes')));
    }
}
