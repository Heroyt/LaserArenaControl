<?php
/**
 * @file      Loader.php
 * @brief     Core\Loader class
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

use Dibi\Exception;
use Lsr\Core\App;
use Lsr\Helpers\Tools\Timer;
use RuntimeException;

/**
 * @class   Loader
 * @brief   Loader class to prevent any unnecessary global variable
 *
 * @package Core
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
class Loader
{

	/**
	 * Initialize everything necessary
	 *
	 * @return void
	 *
	 * @post    URL request is parsed
	 * @post    Database connection established
	 *
	 * @since   1.0
	 * @version 1.0
	 */
	public static function init() : void {

		if (defined('INDEX') && INDEX) {
			Timer::start('core.init.config');
			// Initialize config
			self::initConfig();
			Timer::stop('core.init.config');

			// Initialize app
			Timer::start('core.init.app');
			App::init();
			Timer::stop('core.init.app');
		}

		// Setup database connection
		Timer::start('core.init.db');
		self::initDB();
		Timer::stop('core.init.db');

	}

	/**
	 * Initialize configuration constants
	 *
	 * @since   1.0
	 * @version 1.0
	 */
	private static function initConfig() : void {
		$config = App::getConfig();

		if ($config['General']['PRETTY_URL'] ?? false) {
			App::prettyUrl();
		}
		else {
			App::uglyUrl();
		}
	}

	/**
	 * Initialize database connection
	 *
	 * @return void
	 *
	 * @throws RuntimeException
	 * @since   1.0
	 * @version 1.0
	 */
	public static function initDB() : void {
		if (isset($_ENV['noDb'])) {
			return;
		}
		try {
			DB::init();
		} catch (Exception $e) {
			App::getLogger()->error('Cannot connect to the database!'.$e->getMessage());
			throw new RuntimeException('Cannot connect to the database!', $e->getCode(), $e);
		}
	}

}
