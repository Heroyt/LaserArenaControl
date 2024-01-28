<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace App\Services;

use JsonException;
use Redis;

/**
 * Service for broadcasting WS events to front-end users
 */
class EventService
{

	public const TABLE = 'events';

	public function __construct(private readonly Redis $redis) {
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