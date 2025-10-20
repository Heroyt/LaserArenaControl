<?php

declare(strict_types=1);

namespace App\DataObjects\Request\Settings;

class SaveSystemsData
{
    /** @var SystemData[] */
    public array $systems = [];

    /** @var VestData[] */
    public array $vests = [];

    public function addVest(VestData $vest) : void {
        $this->vests[] = $vest;
    }

    /**
     * @return VestData[]
     */
    public function getVests() : array {
        return $this->vests;
    }

    public function addSystem(SystemData $system) : void {
        $this->systems[] = $system;
    }

    /**
     * @return SystemData[]
     */
    public function getSystems() : array {
        return $this->systems;
    }
}
