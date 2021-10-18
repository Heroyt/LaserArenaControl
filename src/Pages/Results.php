<?php

namespace App\Pages;

use App\Core\Page;

class Results extends Page
{

	protected string $title       = 'Results';
	protected string $description = '';

	public function show() : void {
		$this->view('pages/dashboard/index');
	}

}