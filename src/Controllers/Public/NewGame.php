<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\NewGameTrait;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\Models\MusicMode;
use App\Templates\NewGame\NewGameParams;
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
     * @throws ValidationException
     * @throws Throwable
     */
    public function show(Request $request) : ResponseInterface {
        $this->initNewGameParams($request);
        $this->params->gameModes = GameModeFactory::getAll(['system' => $this->params->system, 'public' => true]);
        $this->params->musicModes = MusicMode::query()->where('public = 1')->orderBy('order')->get();
        $this->initMusicGroups();
        $this->params->addCss = ['pages/newGamePublic.css'];

        /** @var string|null $game */
        $game = $request->getGet('game');

        $this->params->loadGame = !empty($game) ? GameFactory::getByCode($game) : null;

        foreach ($this->decorators as $decorator) {
            if ($decorator->decorates('public') && method_exists($decorator, 'decoratePublic')) {
                $decorator->decoratePublic();
            }
        }

        return $this->view('pages/public/new-game')
                    ->withAddedHeader('Expires', date('D, d M Y H:i:s T', strtotime('+ 1 minutes')));
    }
}
