<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\DataObjects\NewGame\HookedTemplates;
use App\GameModels\Vest;
use App\Gate\Models\MusicGroupDto;
use App\Models\Playlist;
use App\Models\PriceGroup;
use App\Models\System;
use App\Services\FeatureConfig;
use App\Templates\NewGame\NewGameParams;
use LAC\Modules\Core\ControllerDecoratorInterface;
use Lsr\Core\Requests\Request;
use Lsr\Interfaces\RequestInterface;
use Lsr\Interfaces\SessionInterface;
use Lsr\Orm\Exceptions\ModelNotFoundException;

/**
 * @property NewGameParams $params
 */
trait NewGameTrait
{
    public HookedTemplates $hookedTemplates;
    /** @var ControllerDecoratorInterface[] */
    protected array $decorators = [];

    public function __construct(
      private readonly FeatureConfig $featureConfig,
      private readonly SessionInterface $session,
    ) {

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

    protected function initMusicGroups() : void {
        $this->params->musicGroups = [];
        foreach ($this->params->musicModes as $music) {
            if (!$music->public) {
                continue;
            }
            $group = empty($music->group) ? $music->name : $music->group;
            $this->params->musicGroups[$group] ??= new MusicGroupDto($group);
            $this->params->musicGroups[$group]->music[] = $music;
        }
    }

    protected function initNewGameParams(Request $request) : void {
        $this->hookedTemplates = new HookedTemplates();
        $this->params->addedTemplates = $this->hookedTemplates;
        $this->params->featureConfig = $this->featureConfig;

        $this->params->systems = System::getActive();

        $systemId = $request->getGet('system');
        if ($systemId === null) {
            $systemId = $this->session->get('active_lg_system');
        }
        if ($systemId === null) {
            $this->params->system = System::getDefault();
        }
        else if (is_numeric($systemId)) {
            try {
                $this->params->system = System::get((int) $systemId);
            } catch (ModelNotFoundException) {
                $this->params->system = System::getDefault();
            }
        }
        else {
            $this->params->system = array_find(
              $this->params->systems,
              static fn(System $system) => $system->type->value === $systemId
            );
        }

        if ($this->params->system === null) {
            $this->params->system = first($this->params->systems);
        }
        $this->session->set('active_lg_system', $this->params->system->id);

        $this->params->vests = Vest::getForSystem($this->params->system);
        $this->params->colors = $this->params->system->type->getColors();
        $this->params->teamNames = $this->params->system->type->getTeamNames();
        $this->params->playlists = Playlist::getAll();
        $this->params->priceGroups = PriceGroup::getAll();
        $this->params->priceGroupsAll = PriceGroup::query()->get(); // Get event the deleted price groups
    }
}
