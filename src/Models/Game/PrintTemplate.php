<?php

namespace App\Models\Game;

use App\Core\AbstractModel;
use App\Models\Game\Enums\PrintOrientation;

class PrintTemplate extends AbstractModel
{

	public const TABLE       = 'print_templates';
	public const PRIMARY_KEY = 'id_template';
	public const DEFINITION  = [
		'slug'        => ['validators' => ['required']],
		'name'        => ['validators' => ['required']],
		'description' => [],
		'orientation' => ['class' => PrintOrientation::class],
	];

	public string           $slug        = '';
	public string           $name        = '';
	public ?string          $description = '';
	public PrintOrientation $orientation = PrintOrientation::landscape;

}