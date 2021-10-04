<?php


namespace App\Core\Middleware;


use App\Core\Request;
use App\Core\Routing\Middleware;

class CSRFCheck implements Middleware
{

	/**
	 * @param Request $request
	 *
	 * @return bool
	 */
	public function handle(Request $request) : bool {
		$csrfName = implode('/', $request->path);
		if (formValid($csrfName)) {
			$request->query['error'] = lang('Požadavek vypršel, zkuste to znovu.', context: 'errors');
			return false;
		}
		return true;
	}

}
