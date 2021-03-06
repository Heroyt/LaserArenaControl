<?php

namespace App\Controllers;

use Lsr\Core\Controller;

class Dashboard extends Controller
{

	protected string $title       = 'Dashboard';
	protected string $description = '';

	public function show() : void {
		$this->view('pages/dashboard/index');
	}

}