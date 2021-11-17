<?php

namespace App\Pages;

use App\Core\Page;
use App\Core\Request;

class GamesList extends Page
{

	protected string $title       = 'Games list';
	protected string $description = '';

	public function show() : void {
		$this->view('pages/dashboard/index');
	}

	public function game(Request $request) : void {
		$this->view('pages/dashboard/index');
	}

}