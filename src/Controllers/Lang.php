<?php

namespace App\Controllers;

use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

class Lang extends Controller
{
    public function setLang(Request $request, string $lang) : ResponseInterface {
        /** @var string[]|string $redirect */
        $redirect = $request->getGet('redirect', []);
        return $this->app
          ->redirect($redirect)
          ->withAddedHeader('Set-Cookie', 'lang="'.$lang.'"; Max-Age=2592000');
    }
}
