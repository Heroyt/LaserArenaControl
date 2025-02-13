<?php
declare(strict_types=1);

namespace App\Models;

use Lsr\ObjectValidation\Attributes\IntRange;
use Lsr\Orm\Attributes\PrimaryKey;
use Stringable;

#[PrimaryKey('id_system')]
class System extends BaseModel implements Stringable
{

    public const string TABLE = 'systems';

    public string $name;
    public SystemType $type;
    public string $systemIp = '';
    public string $resultsDir = '';
    public string $gameLoadDir = '';
    public string $musicDir = '';
    #[IntRange(1, 255)]
    public int $columnCount = 15;
    #[IntRange(1, 255)]
    public int $rowCount = 15;
    public bool $default = false;
    public bool $active = true;

    public static function getDefault(bool $cache = true) : ?System {
        return self::query()->where('[default] = 1')->first($cache);
    }

    /**
     * @param  bool  $cache
     * @return System[]
     */
    public static function getActive(bool $cache = true) : array {
        return self::query()->where('active = 1')->get($cache);
    }

    /**
     * @param  SystemType  $type
     * @param  bool  $cache
     * @return System[]
     */
    public static function getForType(SystemType $type, bool $cache = true) : array {
        return self::query()->where('type = %s', $type->value)->get($cache);
    }

    public function __toString() : string {
        return $this->type->value;
    }
}