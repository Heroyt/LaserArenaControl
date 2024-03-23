<?php

namespace App\Controllers;

use App\Core\App;
use Lsr\Core\Controllers\Controller;
use Lsr\Core\Requests\Request;
use Lsr\Core\Session;
use Lsr\Core\Templating\Latte;
use Psr\Http\Message\ResponseInterface;

class Lang extends Controller
{

	public function __construct(Latte $latte, private readonly Session $session) {
		parent::__construct($latte);
	}

	public function setLang(Request $request, string $lang): ResponseInterface {
		$this->session->set('lang', $lang);
		return App::redirect($request->getGet('redirect', []));
	}

}