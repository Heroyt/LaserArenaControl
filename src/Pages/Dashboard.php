<?php

namespace App\Pages;

use App\Core\Page;

class Dashboard extends Page
{

	protected string $title       = 'Dashboard';
	protected string $description = '';

	public function show() : void {
		$this->view('pages/dashboard/index');
	}

}