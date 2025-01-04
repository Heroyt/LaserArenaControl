<?php

namespace App\Gate\Screens\Results;

use App\GameModels\Game\Game;
use App\Gate\Screens\GateScreen;
use App\Gate\Screens\WithSettings;
use App\Gate\Settings\ResultsSettings;

/**
 * @extends WithSettings<ResultsSettings>
 */
interface ResultsScreenInterface extends WithSettings
{

    public function isActive() : bool;

    public function setGame(?Game $game) : GateScreen;
    
}
