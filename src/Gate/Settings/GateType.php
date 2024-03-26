<?php

namespace App\Gate\Settings;

use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_gate')]
class GateType extends Model
{

	public const TABLE = 'gate';

	public string $name;

}