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

/**
 * Add a trailing slash to a string (file/directory path)
 *
 * @param string $string
 *
 * @return string
 */
function trailingUnSlashIt(string $string) : string {
	if (substr($string, -1) === DIRECTORY_SEPARATOR) {
		$string = substr($string, 0, -1);
	}
	return $string;
}