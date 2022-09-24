<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools;

use Exception;
use RuntimeException;
use Socket;

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
	 */
	public static function sendCommand(string $ip, string $command, string $parameters = '') : string {
		/** @var Socket|false $sock */
		$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($sock === false) {
			throw new RuntimeException(lang('Nepodařilo se vytvořit socket.'));
		}
		$res = socket_connect($sock, $ip, self::PORT);
		if ($res === false) {
			throw new RuntimeException(lang('Nepodařilo se připojit k TCP serveru ('.$ip.':'.self::PORT.').'));
		}
		/** @var int|false $a */
		$a = socket_write($sock, $command.':'.$parameters);
		$response = '';
		if ($a !== false) {
			while ($out = socket_read($sock, 2048)) {
				$response .= $out;
			}
		}
		socket_close($sock);
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