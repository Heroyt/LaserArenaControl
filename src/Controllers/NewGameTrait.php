<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\App;
use App\DataObjects\NewGame\HookedTemplates;
use App\GameModels\Factory\GameFactory;
use App\GameModels\Vest;
use App\Gate\Models\MusicGroupDto;
use App\Models\Playlist;
use App\Models\PriceGroup;
use App\Services\FeatureConfig;
use App\Templates\NewGame\NewGameParams;
use LAC\Modules\Core\ControllerDecoratorInterface;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;

trait NewGameTrait {

    public HookedTemplates $hookedTemplates;
    /** @var ControllerDecoratorInterface[] */
    protected array $decorators = [];

    public function __construct(
      private readonly FeatureConfig $featureConfig,
    ) {
        parent::__construct();
        $this->params = new NewGameParams();
    }

    protected function baseInit(RequestInterface $request) : void {
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
    protected function initMusicGroups(): void {
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
    protected function initNewGameParams(Request $request): void {
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
}