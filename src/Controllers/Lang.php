<?php

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class Lang extends Controller
{
    public function setLang(Request $request, string $lang) : ResponseInterface {
        return $this->app
          ->redirect($request->getGet('redirect', []))
          ->withAddedHeader('Set-Cookie', 'lang="'.$lang.'"; Max-Age=2592000');
    }
}
