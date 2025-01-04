<?php

namespace App\Models\DataObjects\NewGame;

class PlayerLoadData
{
    public string $name = '';
    public int | string $vest = '';
    public bool $vip = false;
    public int | string | null $teamNum = null;
    public int | string | null $color = null;
    public string $code = '';
}
