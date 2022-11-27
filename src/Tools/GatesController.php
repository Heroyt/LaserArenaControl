<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Tools;

use Exception;
use RuntimeException;
use Socket;

/**
 * Controller for sending UDP requests to control gates
 */
class GatesController
{

	public const TIMEOUT       = 10; // 10s
	public const PORT          = 666;
	public const START_COMMAND = '0105010080';
	public const END_COMMAND   = '0105020080';

	/**
	 * @param string $ip
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function start(string $ip) : int {
		return self::sendCommand($ip, (string) hex2bin(self::START_COMMAND));
	}

	/**
	 * @param string $ip
	 * @param string $command
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function sendCommand(string $ip, string $command) : int {
		/** @var Socket|false $sock */
		$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if ($sock === false) {
			throw new RuntimeException(lang('Nepodařilo se vytvořit socket.'));
		}
		$res = socket_connect($sock, $ip, self::PORT);
		if ($res === false) {
			throw new RuntimeException(sprintf(lang('Nepodařilo se připojit k socket serveru (%s:%d).'), $ip, self::PORT));
		}
		$a = socket_write($sock, $command, 5);
		if ($a === false) {
			socket_close($sock);
			return 0;
		}

		if (socket_recv($sock, $reply, 2045, MSG_WAITALL) === false) {
			$errCode = socket_last_error();
			$errMsg = socket_strerror($errCode);
			throw new RuntimeException(sprintf(lang('Nepodařilo se přijmout odpověď od serveru (%s - %s)'), $errCode, $errMsg));
		}
		socket_close($sock);
		return $a;
	}

	/**
	 * @param string $ip
	 *
	 * @return int
	 * @throws Exception
	 */
	public static function end(string $ip) : int {
		return self::sendCommand($ip, (string) hex2bin(self::END_COMMAND));
	}

}