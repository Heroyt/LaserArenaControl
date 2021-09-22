<?php


namespace App\Core\Middleware;


use App\Core\Request;
use App\Core\Routing\Middleware;

class CSRFCheck implements Middleware
{

	public function handle(Request $request) : bool {
		$csrfName = implode('/', $request->path);
		if (formValid($csrfName)) {
			$request->query['error'] = 'Požadavek vypršel, zkuste to znovu.';
			return false;
		}
		return true;
	}

}
