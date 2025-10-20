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
    ) {}


    public function translate(Request $request) : ResponseInterface {
        $string = $request->getGet('string');
        assert(is_string($string), 'string parameter is required and must be a string');
        return $this->respond(
          $this->translations->translate(
                     $string,
            plural : $request->getGet('plural'),
            num    : (int) ($request->getGet('count', 1)),
            context: $request->getGet('context', null),
            domain : $request->getGet('domain', null),
          )
        );
    }
}
