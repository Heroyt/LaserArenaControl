<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use App\Core\App;
use JsonException;
use Lsr\Core\Config;
use Redis;

/**
 * Service for broadcasting WS events to front-end users
 */
class EventService
{

	public const TABLE = 'events';

	private static string $eventUrl;

	public function __construct(private readonly Redis $redis) {
	}

	public static function getEventUrl(): string {
		if (!isset(self::$eventUrl)) {
			/** @var Config $config */
			$config = App::getServiceByType(Config::class);
			self::$eventUrl = (App::isSecure() ? 'https://' : 'http://') .
				($config->getConfig(
					'ENV'
				)['EVENT_URL'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost') . ':' . self::getEventPort());
		}
		return self::$eventUrl;
	}

	public static function getEventPort(): int {
		return EVENT_PORT;
	}

	/**
	 * Trigger a new event to be broadcast
	 *
	 * @param string|array<string,mixed> $message Event content
	 *
	 * @return bool Success
	 * @throws JsonException
	 */
	public function trigger(string $type, string|array $message): bool {
		if (is_array($message)) {
			$message = json_encode($message, JSON_THROW_ON_ERROR);
		}
		$id = $this->redis->xAdd('events:' . $type, '*', ['message' => $message], 10, true);
		return !empty($id);
	}

}