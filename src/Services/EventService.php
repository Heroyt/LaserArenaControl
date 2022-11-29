<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */
namespace App\Services;

use Dibi\Exception;
use Dibi\Row;
use Lsr\Core\DB;
use Lsr\Logging\Logger;

/**
 * Service for broadcasting WS events to front-end users
 */
class EventService
{

	public const TABLE = 'events';

	private static Logger $logger;

	public static function getEventPort() : int {
		return EVENT_PORT;
	}

	/**
	 * Trigger a new event to be broadcast
	 *
	 * @param string $message Event content
	 *
	 * @return bool Success
	 */
	public static function trigger(string $message) : bool {
		try {
			DB::insert(self::TABLE, ['message' => $message]);
			return true;
		} catch (Exception $e) {
			self::getLogger()->error($e->getMessage());
			self::getLogger()->debug($e->getTraceAsString());
		}
		return false;
	}

	/**
	 * @return Logger
	 */
	private static function getLogger() : Logger {
		if (!isset(self::$logger)) {
			self::$logger = new Logger(LOG_DIR, 'events');
		}
		return self::$logger;
	}

	/**
	 * Get all events that are not flagged as sent yet
	 *
	 * @return Row[] Array of events ordered by their date from the most recent (descending)
	 */
	public static function getUnsent(bool $dev = false) : array {
		return DB::select('events', '*')->where('%n = 0', $dev ? 'sent_dev' : 'sent')->orderBy('datetime')->desc()->fetchAll(cache: false);
	}

	/**
	 * Flag given event ids as sent
	 *
	 * @param int[] $ids Event ids to flag
	 *
	 * @return bool Success
	 */
	public static function updateSent(array $ids, bool $dev = false) : bool {
		try {
			DB::update('events', [($dev ? 'sent_dev' : 'sent') => 1], ['id_event IN %in', $ids]);
			return true;
		} catch (Exception $e) {
			self::getLogger()->error($e->getMessage());
			self::getLogger()->debug($e->getTraceAsString());
		}
		return false;
	}

}