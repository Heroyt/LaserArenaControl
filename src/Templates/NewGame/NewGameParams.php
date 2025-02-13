<?php

namespace App\Templates\NewGame;

use App\DataObjects\NewGame\HookedTemplates;
use App\GameModels\Game\Game;
use App\GameModels\Game\GameModes\AbstractMode;
use App\GameModels\Vest;
use App\Gate\Models\MusicGroupDto;
use App\Models\MusicMode;
use App\Models\Playlist;
use App\Models\PriceGroup;
use App\Models\System;
use App\Services\FeatureConfig;
use Lsr\Core\Controllers\TemplateParameters;

class NewGameParams extends TemplateParameters
{
    public HookedTemplates $addedTemplates;
    public FeatureConfig $featureConfig;
    public ?Game $loadGame = null;
    public System $system;
    /** @var System[] */
    public array $systems = [];
    /** @var Vest[] */
    public array $vests = [];
    /** @var string[] */
    public array $colors = [];
    /** @var string[] */
    public array $teamNames = [];
    /** @var AbstractMode[] */
    public array $gameModes = [];
    /** @var MusicMode[] */
    public array $musicModes = [];
    /** @var MusicGroupDto[] */
    public array $musicGroups = [];
    /** @var Playlist[] */
    public array $playlists = [];
    /** @var string[] */
    public array $gateActions = [];

    /** @var PriceGroup[] */
    public array $priceGroups = [];
    /** @var PriceGroup[] */
    public array $priceGroupsAll = [];
}
