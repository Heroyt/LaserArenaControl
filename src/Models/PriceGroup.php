<?php

namespace App\Models;

use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

/**
 *
 */
#[PrimaryKey('id_price')]
class PriceGroup extends Model
{

    public const string TABLE = 'price_groups';

    public string $name;

    /**
     * @var int Price is stored as an int with 2 decimal places of precision (*100)
     */
    public int $price;

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