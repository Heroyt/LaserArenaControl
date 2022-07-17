<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Core;


use Throwable;

abstract class ApiController extends Controller
{

	/**
	 * @param string|array|object $data
	 * @param int                 $code
	 * @param string[]            $headers
	 *
	 * @return never
	 * @throws Throwable On json_encode error
	 */
	public function respond(string|array|object $data, int $code = 200, array $headers = []) : never {
		http_response_code($code);

		$dataNormalized = '';
		if (is_string($data)) {
			$dataNormalized = $data;
		}
		else if (is_array($data) || is_object($data)) {
			try {
				$dataNormalized = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			} catch (Throwable $e) {
				App::getLogger()->error('JSON encode error - '.$e->getMessage());
				App::getLogger()->debug(print_r($data, true));
				throw $e;
			}
			$headers['Content-Type'] = 'application/json';
		}


		foreach ($headers as $name => $value) {
			header($name.': '.$value);
		}

		echo $dataNormalized;
		exit;
	}

}