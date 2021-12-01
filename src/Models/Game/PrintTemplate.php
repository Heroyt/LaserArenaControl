<?php

namespace App\Models\Game;

use App\Core\AbstractModel;

class PrintTemplate extends AbstractModel
{

	public const TABLE       = 'print_templates';
	public const PRIMARY_KEY = 'id_template';
	public const DEFINITION  = [
		'slug'        => ['validators' => ['required']],
		'name'        => ['validators' => ['required']],
		'description' => [],
	];

	public string  $slug        = '';
	public string  $name        = '';
	public ?string $description = '';

}