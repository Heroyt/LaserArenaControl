<?php

namespace App\Controllers\Api;

use App\Services\LaserLiga\LigaApi;
use Lsr\Core\Controllers\Controller;
use Psr\Http\Message\ResponseInterface;

class LaserLiga extends Controller
{
    public function __construct(
      private readonly LigaApi $api
    ) {
        parent::__construct();
    }

    public function highlights(string $code) : ResponseInterface {
        $response = $this->api->get('/api/games/'.$code.'/highlights');
        $response->getBody()->rewind();
        $contents = $response->getBody()->getContents();

        if ($response->getStatusCode() !== 200) {
            return $this->respond(['error' => $contents, 'code' => $response->getStatusCode()], 500);
        }

        /** @var array{type:string,score:int,value:string,description:string}[] $highlights */
        $highlights = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $this->respond($highlights);
    }
}
