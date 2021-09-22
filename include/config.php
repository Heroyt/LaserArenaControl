<?php
/**
 * @file      config.php
 * @brief     App configuration
 * @details   Contains all constants and settings
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 */

use App\Core\App;

/** Directory containing log files */
const LOG_DIR = ROOT.'logs/';
/** Directory containing temporary files */
const TMP_DIR = ROOT.'temp/';
/** Directory containing template files */
const TEMPLATE_DIR = ROOT.'templates/';
/** Directory for user uploads */
const UPLOAD_DIR = ROOT.'upload/';
/** Directory for files hidden from the user */
const PRIVATE_DIR = ROOT.'private/';

/** If in production */
define('PRODUCTION', App::isProduction());

/**
 * @var $DEBUG
 * @brief All debug information
 */
$DEBUG = [
	'DB' => [],
];
