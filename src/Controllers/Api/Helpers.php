<?php

namespace App\Controllers\Api;

use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;

class Helpers extends ApiController
{


	#[Get('/api/helpers/translate')]
	public function translate(Request $request) : never {
		$this->respond(
			lang(
				$request->get['string'],
				$request->get['plural'] ?? null,
				(int) ($request->get['count'] ?? 1),
				$request->get['context'] ?? ''
			)
		);
	}

}