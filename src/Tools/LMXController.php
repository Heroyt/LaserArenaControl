<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools;

use App\Exceptions\ConnectionTimeoutException;
use Exception;

/**
 * Controller for sending TCP requests to control the LaserMaxx console
 */
class LMXController
{

	public const PORT               = 8081;
	public const LOAD_COMMAND       = 'load';
	public const START_COMMAND      = 'start';
	public const END_COMMAND        = 'end';
	public const GET_STATUS_COMMAND = 'status';

	public const DEFAULT_TIMEOUT  = 30;
	public const COMMAND_TIMEOUTS = [
		self::GET_STATUS_COMMAND => 10,
	];

	/**
	 * Get a current game status
	 *
	 * @param string $ip
	 *
	 * @return string 'ARMED'|'STANDBY'|'PLAYING'
	 * @throws Exception
	 */
	public static function getStatus(string $ip) : string {
		return self::sendCommand($ip, self::GET_STATUS_COMMAND);
	}

	/**
	 * @param string $ip
	 * @param string $command
	 * @param string $parameters
	 *
	 * @return string Response
	 * @throws ConnectionTimeoutException
	 */
	public static function sendCommand(string $ip, string $command, string $parameters = '') : string {
		$timeout = self::COMMAND_TIMEOUTS[$command] ?? self::DEFAULT_TIMEOUT;
		$fp = @fsockopen($ip, self::PORT, $errno, $errstr, $timeout);
		if (!$fp) {
			throw new ConnectionTimeoutException(lang('Nepodařilo se připojit k TCP serveru ('.$ip.':'.self::PORT.'). '.$errstr.' ('.$errno.')'), $errno, $timeout);
		}
		fwrite($fp, $command.':'.$parameters);
		$response = '';
		while (!feof($fp)) {
			$response .= fgets($fp, 128);
		}
		fclose($fp);
		return $response;
	}

	/**
	 * Start a new game
	 *
	 * @param string $ip
	 *
	 * @return string
	 * @throws Exception
	 */
	public static function start(string $ip) : string {
		return self::sendCommand($ip, self::START_COMMAND);
	}

	/**
	 * Load a new game
	 *
	 * @param string $ip
	 * @param string $gameMode Game mode to load
	 *
	 * @return string Response
	 */
	public static function load(string $ip, string $gameMode) : string {
		return self::sendCommand($ip, self::LOAD_COMMAND, $gameMode);
	}

	/**
	 * Stop a currently running game
	 *
	 * @param string $ip
	 *
	 * @return string response
	 * @throws Exception
	 */
	public static function end(string $ip) : string {
		return self::sendCommand($ip, self::END_COMMAND);
	}

}