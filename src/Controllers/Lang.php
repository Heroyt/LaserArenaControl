<?php

namespace App\Controllers;

use App\Core\App;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Session;
use Lsr\Core\Translations;
use Psr\Http\Message\ResponseInterface;

class Lang extends Controller
{
    public function setLang(Request $request, string $lang) : ResponseInterface {
        return $this->app
          ->redirect($request->getGet('redirect', []))
          ->withAddedHeader('Set-Cookie', 'lang="'.$lang.'"; Max-Age=2592000');
    }

}