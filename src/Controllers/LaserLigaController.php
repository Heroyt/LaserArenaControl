<?php

namespace App\Controllers;

use App\Services\LigaApi;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Templating\Latte;

class LaserLigaController extends Controller
{

	public function __construct(
		Latte                    $latte,
		private readonly LigaApi $api
	) {
		parent::__construct($latte);
	}

	public function highlights(string $code): never {
		$response = $this->api->get('/api/games/' . $code . '/highlights');
		$response->getBody()->rewind();
		$contents = $response->getBody()->getContents();

		if ($response->getStatusCode() !== 200) {
			$this->respond(['error' => $contents, 'code' => $response->getStatusCode()], 500);
		}

		/** @var array{type:string,score:int,value:string,description:string}[] $highlights */
		$highlights = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

		$this->respond($highlights);
	}

}