<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Core\Routing;


use App\Core\Request;

interface Middleware
{

	/**
	 * Handles a request
	 *
	 * @param Request $request
	 *
	 * @return bool
	 */
	public function handle(Request $request) : bool;

}
