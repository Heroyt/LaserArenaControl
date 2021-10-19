<?php
/**
 * @file      Constants.php
 * @brief     Core\Constants class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

/**
 * @package Core
 * @brief   Core classes
 */

namespace App\Core;

/**
 * @class   Constants
 * @brief   Constants defined in a class
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @package Core
 * @version 1.0
 * @since   1.0
 */
class Constants
{

	// Logging constants

	/** Constant marking an error message */
	public const E = 'ERROR';
	/** Constant marking a warning message */
	public const W = 'WARNING';
	/** Constant marking a notice message */
	public const N = 'NOTICE';
	/** Constant marking an ordinary message */
	public const M = 'MESSAGE';

	/** @var string Name of the app */
	public const SITE_NAME = 'LaserArenaControl';

	public const MONTH_NAMES = [
		1  => 'Leden',
		2  => 'Únor',
		3  => 'Březen',
		4  => 'Duben',
		5  => 'Květen',
		6  => 'Červen',
		7  => 'Červenec',
		8  => 'Srpen',
		9  => 'Září',
		10 => 'Říjen',
		11 => 'Listopad',
		12 => 'Prosinec',
	];

	public const ALLOWED_IMAGES = [
		'jpg',
		'gif',
		'png',
		'jpeg',
	];

	public const SORT_ASC  = 'ASC';
	public const SORT_DESC = 'DESC';
}
