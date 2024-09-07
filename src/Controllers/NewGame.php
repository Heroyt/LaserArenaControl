<?php

/** @noinspection PhpDynamicFieldDeclarationInspection */

namespace App\Controllers;

use App\Core\App;
use App\DataObjects\NewGame\HookedTemplates;
use App\Exceptions\GameModeNotFoundException;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Factory\GameModeFactory;
use App\GameModels\Vest;
use App\Gate\Models\GateScreenModel;
use App\Gate\Models\MusicGroupDto;
use App\Models\MusicMode;
use App\Models\Playlist;
use App\Models\PriceGroup;
use App\Services\FeatureConfig;
use App\Templates\NewGame\NewGameParams;
use LAC\Modules\Core\ControllerDecoratorInterface;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * @property NewGameParams $params
 */
class NewGame extends Controller
{
    public HookedTemplates $hookedTemplates;
    protected string $title = 'Nová hra';
    /** @var ControllerDecoratorInterface[] */
    private array $decorators = [];

    public function __construct(
        private readonly FeatureConfig $featureConfig,
    ) {
        parent::__construct();
        $this->params = new NewGameParams();
    }

    public function init(RequestInterface $request): void {
        $this->params['addCss'] = [];
        $this->params['addJs'] = [];
        parent::init($request);
        /** @var array<string, mixed> $decorators */
        $decorators = App::getContainer()->findByTag('newGameDecorator');
        bdump($decorators);
        foreach ($decorators as $name => $attributes) {
            /** @var ControllerDecoratorInterface $decorator */
            /** @phpstan-ignore-next-line */
            $this->decorators[$name] = $decorator = App::getService($name);
            $decorator->setController($this)->init();
        }
    }

    private function initNewGameParams(Request $request): void {
        $this->hookedTemplates = new HookedTemplates();
        $this->params->addedTemplates = $this->hookedTemplates;
        $this->params->featureConfig = $this->featureConfig;

        /** @phpstan-ignore-next-line */
        $this->params->system = $request->getGet('system', first(GameFactory::getSupportedSystems()));
        $this->params->vests = Vest::getForSystem($this->params->system);
        $this->params->colors = GameFactory::getAllTeamsColors()[$this->params->system];
        $this->params->teamNames = GameFactory::getAllTeamsNames()[$this->params->system];
        $this->params->playlists = Playlist::getAll();
        $this->params->priceGroups = PriceGroup::getAll();
        $this->params->priceGroupsAll = PriceGroup::query()->get(); // Get event the deleted price groups

    }

    private function initMusicGroups(): void {
        $this->params->musicGroups = [];
        foreach ($this->params->musicModes as $music) {
            if (!$music->public) {
                continue;
            }
            $group = $music->group ?? $music->name;
            $this->params->musicGroups[$group] ??= new MusicGroupDto($group);
            $this->params->musicGroups[$group]->music[] = $music;
        }
    }

    /**
     * @param  Request  $request
     * @return ResponseInterface
     * @throws GameModeNotFoundException
     * @throws TemplateDoesNotExistException
     * @throws Throwable
     * @throws ValidationException
     */
    public function show(Request $request): ResponseInterface {
        $this->initNewGameParams($request);
        $this->params->gameModes = GameModeFactory::getAll(['system' => $this->params->system]);
        $this->params->musicModes = MusicMode::getAll();
        $this->initMusicGroups();
        $this->params->addCss = ['pages/newGame.css'];

        /** @var string|null $game */
        $game = $request->getGet('game');

        $this->params->loadGame = !empty($game) ? GameFactory::getByCode($game) : null;

        $gateActionScreens = GateScreenModel::query()->where('trigger_value IS NOT NULL')->get();
        $this->params->gateActions = [];
        foreach ($gateActionScreens as $gateActionScreen) {
            $this->params->gateActions[$gateActionScreen->triggerValue] = $gateActionScreen->triggerValue;
        }

        foreach ($this->decorators as $decorator) {
            if ($decorator->decorates('show') && method_exists($decorator, 'decorateShow')) {
                $decorator->decorateShow();
            }
        }

        return $this->view('pages/new-game/index')
                    ->withAddedHeader('Expires', date('D, d M Y H:i:s T', strtotime('+ 1 minutes')));
    }

    /**
     * @param  Request  $request
     * @return ResponseInterface
     * @throws GameModeNotFoundException
     * @throws TemplateDoesNotExistException
     * @throws Throwable
     * @throws ValidationException
     */
    public function public(Request $request): ResponseInterface {
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

        return $this->view('pages/new-game/public')
                    ->withAddedHeader('Expires', date('D, d M Y H:i:s T', strtotime('+ 1 minutes')));
    }
}
