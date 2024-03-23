<?php

namespace App\Controllers\Api;

use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Routing\Attributes\Get;
use Psr\Http\Message\ResponseInterface;

class Helpers extends ApiController
{


	#[Get('/api/helpers/translate')]
	public function translate(Request $request): ResponseInterface {
		return $this->respond(
			lang(
				$request->getGet('string'),
				$request->getGet('plural', null),
				(int)($request->getGet('count', 1)),
				$request->getGet('context', '')
			)
		);
	}

}