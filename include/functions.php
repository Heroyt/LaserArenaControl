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
 * @param array $array
 *
 * @return mixed
 * @phpstan-ignore-next-line
 */
function last(array $array) : mixed {
	return end($array);
}