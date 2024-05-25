<?php

namespace App\Controllers\Api;

use Lsr\Core\Controllers\ApiController;
use Lsr\Core\Requests\Request;
use Lsr\Core\Translations;
use Psr\Http\Message\ResponseInterface;

class Helpers extends ApiController
{

    public function __construct(
      private readonly Translations $translations,
    ) {
        parent::__construct();
    }


    public function translate(Request $request) : ResponseInterface {
        return $this->respond(
          $this->translations->translate(
                     (string) $request->getGet('string'),
            plural : $request->getGet('plural'),
            num    : (int) ($request->getGet('count', 1)),
            context: $request->getGet('context', null),
            domain : $request->getGet('domain', null),
          )
        );
    }

}