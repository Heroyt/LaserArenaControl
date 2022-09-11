<?php

namespace App\Models;

use Lsr\Core\Models\Attributes\PrimaryKey;
use Lsr\Core\Models\Attributes\Validation\Required;
use Lsr\Core\Models\Attributes\Validation\StringLength;
use Lsr\Core\Models\Model;

#[PrimaryKey('id_music')]
class MusicMode extends Model
{

	public const TABLE = 'music';

	#[Required]
	#[StringLength(1, 20)]
	public string $name;
	#[Required]
	public string $fileName = '';

}