<?php

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
use App\Services\FeatureConfig;
use LAC\Modules\Core\ControllerDecoratorInterface;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Exceptions\ValidationException;
use Lsr\Core\Requests\Request;
use Lsr\Core\Templating\Latte;
use Lsr\Exceptions\TemplateDoesNotExistException;
use Lsr\Interfaces\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class NewGame extends Controller
{

    public HookedTemplates $hookedTemplates;
    protected string $title = 'Nová hra';
    protected string $description = '';
    /** @var ControllerDecoratorInterface[] */
    private array $decorators = [];

    public function __construct(
      private readonly FeatureConfig $featureConfig,
    ) {
        parent::__construct();
    }

    public function init(RequestInterface $request) : void {
        $this->params['addCss'] = [];
        $this->params['addJs'] = [];
        parent::init($request);
        /** @var array<string, mixed> $decorators */
        $decorators = App::getContainer()->findByTag('newGameDecorator');
        bdump($decorators);
        foreach ($decorators as $name => $attributes) {
            /** @var ControllerDecoratorInterface $decorator */
            $this->decorators[$name] = $decorator = App::getService($name);
            $decorator->setController($this)->init();
        }
    }

    /**
     * @return void
     * @throws GameModeNotFoundException
     * @throws ValidationException
     * @throws TemplateDoesNotExistException
     * @throws Throwable
     */
    public function show(Request $request) : ResponseInterface {
        $this->hookedTemplates = new HookedTemplates();
        $this->params['addedTemplates'] = $this->hookedTemplates;
        $this->params['featureConfig'] = $this->featureConfig;
        $this->params['addCss'] = ['pages/newGame.css'];
        $game = $request->getGet('game');

        $this->params['loadGame'] = !empty($game) ? GameFactory::getByCode($game) : null;
        $this->params['system'] = $request->getGet('system', first(GameFactory::getSupportedSystems()));
        $this->params['vests'] = Vest::getForSystem($this->params['system']);
        $this->params['colors'] = GameFactory::getAllTeamsColors()[$this->params['system']];
        $this->params['teamNames'] = GameFactory::getAllTeamsNames()[$this->params['system']];
        $this->params['gameModes'] = GameModeFactory::getAll(['system' => $this->params['system']]);
        $this->params['musicModes'] = MusicMode::getAll();
        $this->params['playlists'] = Playlist::getAll();
        $this->params['musicGroups'] = [];
        foreach ($this->params['musicModes'] as $music) {
            if (!$music->public) {
                continue;
            }
            $group = $music->group ?? $music->name;
            $this->params['musicGroups'][$group] ??= new MusicGroupDto($group);
            $this->params['musicGroups'][$group]->music[] = $music;
        }

        $gateActionScreens = GateScreenModel::query()->where('trigger_value IS NOT NULL')->get();
        $this->params['gateActions'] = [];
        foreach ($gateActionScreens as $gateActionScreen) {
            $this->params['gateActions'][$gateActionScreen->triggerValue] = $gateActionScreen->triggerValue;
        }

        foreach ($this->decorators as $decorator) {
            if ($decorator->decorates('show') && method_exists($decorator, 'decorateShow')) {
                $decorator->decorateShow();
            }
        }

        return $this->view('pages/new-game/index');
    }

}