<?php

namespace App\Pages;

use App\Core\Page;

class GamesList extends Page
{

	protected string $title       = 'Games list';
	protected string $description = '';

	public function show() : void {
		$this->view('pages/dashboard/index');
	}

	public function game() : void {
		$this->view('pages/dashboard/index');
	}

}