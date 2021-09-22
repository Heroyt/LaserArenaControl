<?php
/**
 * @file      functions.php
 * @brief     Main functions
 * @details   File containing all main functions for the app.
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use App\Core\App;

/**
 * Get latte template file path by template name
 *
 * @param string $name Template file name
 *
 * @return string
 *
 * @throws RuntimeException
 *
 * @version 0.1
 * @since   0.1
 */
function getTemplate(string $name) : string {
	if (!file_exists(TEMPLATE_DIR.$name.'.latte')) {
		throw new RuntimeException('Cannot find latte template file ('.$name.')');
	}
	return TEMPLATE_DIR.$name.'.latte';
}

/**
 * Generate a form token to protect against CSRF
 *
 * @param string $prefix
 *
 * @return string
 */
function formToken(string $prefix = '') : string {
	if (empty($_SESSION[$prefix.'_csrf_hash'])) {
		$_SESSION[$prefix.'_csrf_hash'] = bin2hex(random_bytes(32));
	}
	return $_SESSION[$prefix.'_csrf_hash'];
}

/**
 * Validate a CSRF token
 *
 * @param string $hash
 *
 * @param string $check
 *
 * @return bool
 */
function isTokenValid(string $hash, string $check = '') : bool {
	if (empty($check)) {
		$check = (string) ($_SESSION['_csrf_hash'] ?? '');
	}
	return hash_equals($check, $hash);
}

/**
 * Validate submitted form against csrf
 *
 * @param string $name
 *
 * @return bool
 */
function formValid(string $name) : bool {
	$hash = hash_hmac('sha256', $name, $_SESSION[$name.'_csrf_hash'] ?? '');
	return isTokenValid($_REQUEST['_csrf_token'], $hash);
}

/**
 * Print a bootstrap alert
 *
 * @param string $content
 * @param string $type
 *
 * @return string
 */
function alert(string $content, string $type = 'danger') : string {
	return '<div class="alert alert-'.$type.'">'.$content.'</div>';
}

function not_empty($var) : bool {
	return !empty($var);
}

/**
 * Renders a view from a latte template
 *
 * @param string $template Template name
 * @param array  $params   Template parameters
 * @param bool   $return   If true, returns the HTML as string
 *
 * @return string Can be empty if $return is false
 */
function view(string $template, array $params = [], bool $return = false) : string {
	if ($return) {
		return App::$latte->renderToString(getTemplate($template), $params);
	}
	App::$latte->render(getTemplate($template), $params);
	return '';
}

/**
 * @param float $x
 * @param float $minIn
 * @param float $maxIn
 * @param float $minOut
 * @param float $maxOut
 *
 * @return float
 */
function map(float $x, float $minIn, float $maxIn, float $minOut, float $maxOut) : float {
	return ($x - $minIn) * ($maxOut - $minOut) / ($maxIn - $minIn) + $minOut;
}
