<?php

namespace App\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use OpenApi\Attributes as OA;

/**
 *
 */
#[PrimaryKey('id_price'), OA\Schema]
class PriceGroup extends BaseModel
{
    public const string TABLE = 'price_groups';

    #[OA\Property]
    public string $name;

    /**
     * @var int Price is stored as an int with 2 decimal places of precision (*100)
     */
    #[OA\Property(type: 'float')]
    public int $price;

    #[OA\Property]
    public bool $deleted = false;

    public static function getAll() : array {
        return static::query()->where('[deleted] = 0')->get();
    }

    public function jsonSerialize() : array {
        $data = parent::jsonSerialize();
        $data['price'] = $this->getPrice();
        return $data;
    }

    public function getPrice() : float {
        return $this->price / 100;
    }

    public function setPrice(float | int $price) : void {
        $this->price = (int) ($price * 100);
    }
}
