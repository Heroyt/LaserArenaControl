<?php
declare(strict_types=1);

namespace App\DataObjects\Request\Settings;

use App\GameModels\VestType;
use Lsr\LaserLiga\Enums\VestStatus;

class VestData
{

    public ?string $vest_num = null;
    public ?VestStatus $status = null;
    public ?VestType $type = null;
    public ?string $info = null;
    public ?int $col = null;
    public ?int $row = null;

}